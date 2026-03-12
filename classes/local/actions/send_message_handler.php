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
 * Handler for sending Moodle messages to users.
 *
 * Uses Moodle's core message_send() function from lib/messagelib.php.
 * Supports placeholder replacement in subject and body fields.
 * This action is always available as messaging is a core Moodle feature.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\actions;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Action handler for sending messages.
 *
 * Sends notification messages via Moodle's messaging API. Supports
 * placeholder replacement for personalised content.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_message_handler implements action_handler {
    /**
     * Get the action type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'send_message';
    }

    /**
     * Check availability — messaging is always available in Moodle core.
     *
     * @return array ['available' => bool, 'error' => string|null]
     */
    public function check_availability(): array {
        return ['available' => true, 'error' => null];
    }

    /**
     * Send a message to the user from the event payload.
     *
     * @param object $data Action data with action_config and event_payload.
     * @return array Structured result.
     */
    public function execute(object $data): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/lib/messagelib.php');

        // Get recipient user from event payload.
        $userid = $data->event_payload->user->id ?? null;
        if (!$userid) {
            return [
                'success' => false,
                'error' => 'User ID not found in event payload',
                'error_code' => error_codes::INVALID_PAYLOAD,
                'retry' => false,
            ];
        }

        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return [
                'success' => false,
                'error' => "User ID {$userid} not found",
                'error_code' => error_codes::USER_NOT_FOUND,
                'retry' => false,
            ];
        }

        // Process message template with placeholders.
        $subject = $this->process_placeholders(
            $data->action_config->subject ?? '',
            $data->event_payload
        );
        $body = $this->process_placeholders(
            $data->action_config->body ?? '',
            $data->event_payload
        );

        // Build message object.
        $message = new \core\message\message();
        $message->component = 'local_mc_plugin';
        $message->name = 'automation';
        $message->userfrom = $this->resolve_sender_user($data);
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = nl2br(s($body));
        $message->smallmessage = $subject;
        $message->notification = 1;

        // Send the message.
        $messageid = message_send($message);

        if (!$messageid) {
            return [
                'success' => false,
                'error' => 'Failed to send message',
                'error_code' => error_codes::MESSAGE_SEND_FAILED,
                'retry' => true,
            ];
        }

        return [
            'success' => true,
            'result' => [
                'status' => 'sent',
                'message_id' => $messageid,
                'recipient' => $user->email,
            ],
        ];
    }

    /**
     * Resolve the sender user based on action configuration.
     *
     * Supported values:
     * - system: Moodle no-reply user
     * - teacher: first enrolled teacher in the related course
     *
     * Falls back to no-reply if no eligible teacher can be resolved.
     *
     * @param object $data Action data with action_config and event_payload.
     * @return object User-like record for message->userfrom
     */
    private function resolve_sender_user(object $data): object {
        global $DB;

        $sender = $data->action_config->sender ?? 'system';
        if ($sender !== 'teacher') {
            return \core_user::get_noreply_user();
        }

        $courseid = isset($data->event_payload->course->id) ? (int) $data->event_payload->course->id : 0;
        if (!$courseid) {
            return \core_user::get_noreply_user();
        }

        $teacherroles = $DB->get_records_list(
            'role',
            'archetype',
            ['editingteacher', 'teacher'],
            'sortorder ASC',
            'id'
        );
        if (empty($teacherroles)) {
            return \core_user::get_noreply_user();
        }

        $roleids = [];
        foreach ($teacherroles as $role) {
            $roleids[] = (int) $role->id;
        }

        [$rolesql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'role');
        $params['courseid'] = $courseid;
        $params['contextlevel'] = CONTEXT_COURSE;

        $sql = "SELECT u.*
                  FROM {user} u
                  JOIN {user_enrolments} ue
                    ON ue.userid = u.id
                   AND ue.status = 0
                  JOIN {enrol} e
                    ON e.id = ue.enrolid
                   AND e.courseid = :courseid
                   AND e.status = 0
                  JOIN {context} cx
                    ON cx.contextlevel = :contextlevel
                   AND cx.instanceid = e.courseid
                  JOIN {role_assignments} ra
                    ON ra.contextid = cx.id
                   AND ra.userid = u.id
                 WHERE ra.roleid {$rolesql}
                   AND u.deleted = 0
                   AND u.suspended = 0
              ORDER BY ra.id ASC";

        $teacher = $DB->get_record_sql($sql, $params, IGNORE_MISSING);
        if ($teacher) {
            return $teacher;
        }

        return \core_user::get_noreply_user();
    }

    /**
     * Replace placeholders like {{user.firstname}} with actual values from the event payload.
     *
     * @param string $template The template string with placeholders.
     * @param object $payload The event payload object.
     * @return string The processed string.
     */
    private function process_placeholders(string $template, object $payload): string {
        $replacements = [
            '{{user.firstname}}' => $payload->user->firstname ?? '',
            '{{user.lastname}}' => $payload->user->lastname ?? '',
            '{{user.email}}' => $payload->user->email ?? '',
            '{{course.fullname}}' => $payload->course->fullname ?? '',
            '{{course.shortname}}' => $payload->course->shortname ?? '',
        ];
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
