<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Handler for issuing certificates via mod_customcert.
 *
 * Calls \mod_customcert\certificate::issue_certificate() to issue a PDF certificate
 * to a user. Checks customcert_issues for duplicates before issuing.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\actions;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Action handler for issuing certificates.
 *
 * Requires the mod_customcert plugin to be installed. Checks for existing
 * certificate issues to maintain idempotency at the Moodle level.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issue_certificate_handler implements action_handler {
    /**
     * Get the action type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'issue_certificate';
    }

    /**
     * Check if mod_customcert is installed.
     *
     * @return array ['available' => bool, 'error' => string|null]
     */
    public function check_availability(): array {
        $plugindir = \core_component::get_component_directory('mod_customcert');
        if ($plugindir === null) {
            return [
                'available' => false,
                'error' => 'mod_customcert plugin is not installed',
            ];
        }
        return ['available' => true, 'error' => null];
    }

    /**
     * Issue a certificate to the user from the event payload.
     *
     * @param object $data Action data with action_config and event_payload.
     * @return array Structured result.
     */
    public function execute(object $data): array {
        global $DB;

        // Check availability.
        $availability = $this->check_availability();
        if (!$availability['available']) {
            return [
                'success' => false,
                'error' => $availability['error'],
                'error_code' => error_codes::PLUGIN_NOT_INSTALLED,
                'retry' => false,
            ];
        }

        // Get certificate.
        $certificateid = $data->action_config->certificate_id;
        $certificate = $DB->get_record('customcert', ['id' => $certificateid]);
        if (!$certificate) {
            return [
                'success' => false,
                'error' => "Certificate ID {$certificateid} not found",
                'error_code' => error_codes::CERTIFICATE_NOT_FOUND,
                'retry' => false,
            ];
        }

        // Get user from event payload.
        $userid = $data->event_payload->user->id ?? null;
        if (!$userid) {
            return [
                'success' => false,
                'error' => 'User ID not found in event payload',
                'error_code' => error_codes::INVALID_PAYLOAD,
                'retry' => false,
            ];
        }

        // Check if already issued (Moodle-level idempotency).
        $existing = $DB->get_record('customcert_issues', [
            'customcertid' => $certificateid,
            'userid' => $userid,
        ]);
        if ($existing) {
            return [
                'success' => true,
                'result' => [
                    'status' => 'already_issued',
                    'certificate_code' => $existing->code,
                    'issued_at' => $existing->timecreated,
                ],
            ];
        }

        // Respect optional notification preference from action config.
        $sendemail = self::normalise_bool($data->action_config->send_email ?? true, true);

        if (!method_exists(\mod_customcert\certificate::class, 'issue_certificate')) {
            return [
                'success' => false,
                'error' => 'mod_customcert is installed but missing issue_certificate API',
                'error_code' => error_codes::PLUGIN_NOT_INSTALLED,
                'retry' => false,
            ];
        }

        // Keep compatibility with customcert versions that may not support
        // the send-email argument in issue_certificate().
        $method = new \ReflectionMethod(\mod_customcert\certificate::class, 'issue_certificate');
        if ($method->getNumberOfParameters() >= 3) {
            $issueid = \mod_customcert\certificate::issue_certificate($certificateid, $userid, $sendemail);
        } else {
            $issueid = \mod_customcert\certificate::issue_certificate($certificateid, $userid);
        }

        if (!$issueid) {
            return [
                'success' => false,
                'error' => "issue_certificate() returned no issue ID for certificate {$certificateid}, user {$userid}",
                'error_code' => error_codes::ACTION_FAILED,
                'retry' => true,
            ];
        }

        // Get the issued record for the code.
        $issue = $DB->get_record('customcert_issues', ['id' => $issueid]);
        if (!$issue) {
            return [
                'success' => false,
                'error' => "Certificate was issued (ID {$issueid}) but the record could not be retrieved",
                'error_code' => error_codes::ACTION_FAILED,
                'retry' => true,
            ];
        }

        return [
            'success' => true,
            'result' => [
                'status' => 'issued',
                'issue_id' => $issueid,
                'certificate_code' => $issue->code,
                'issued_at' => $issue->timecreated,
                'send_email' => $sendemail,
            ],
        ];
    }

    /**
     * Normalize incoming mixed values to boolean.
     *
     * @param mixed $value Raw value.
     * @param bool $default Default when value is not parseable.
     * @return bool
     */
    private static function normalise_bool($value, bool $default): bool {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsed !== null) {
                return $parsed;
            }
        }
        return $default;
    }
}
