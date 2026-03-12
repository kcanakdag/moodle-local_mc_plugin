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
 * Handler for awarding badges to users.
 *
 * Uses Moodle's core badge class to issue badges programmatically.
 * Requires badges to be enabled in site configuration ($CFG->enablebadges).
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\actions;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Action handler for awarding badges.
 *
 * Checks badge_issued for duplicates before awarding. Returns the badge
 * uniquehash on success.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class award_badge_handler implements action_handler {
    /**
     * Get the action type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'award_badge';
    }

    /**
     * Check if badges are enabled in site configuration.
     *
     * @return array ['available' => bool, 'error' => string|null]
     */
    public function check_availability(): array {
        global $CFG;

        if (empty($CFG->enablebadges)) {
            return [
                'available' => false,
                'error' => 'Badges are disabled in site configuration',
            ];
        }
        return ['available' => true, 'error' => null];
    }

    /**
     * Award a badge to the user from the event payload.
     *
     * @param object $data Action data with action_config and event_payload.
     * @return array Structured result.
     */
    public function execute(object $data): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/badgeslib.php');
        require_once($CFG->dirroot . '/badges/classes/badge.php');

        // Check availability.
        $availability = $this->check_availability();
        if (!$availability['available']) {
            return [
                'success' => false,
                'error' => $availability['error'],
                'error_code' => error_codes::BADGES_DISABLED,
                'retry' => false,
            ];
        }

        // Get badge.
        $badgeid = $data->action_config->badge_id;
        $badgerecord = $DB->get_record('badge', ['id' => $badgeid]);
        if (!$badgerecord) {
            return [
                'success' => false,
                'error' => "Badge ID {$badgeid} not found",
                'error_code' => error_codes::BADGE_NOT_FOUND,
                'retry' => false,
            ];
        }

        $badge = new \badge($badgeid);

        // Check badge is active.
        if ($badge->status != BADGE_STATUS_ACTIVE && $badge->status != BADGE_STATUS_ACTIVE_LOCKED) {
            return [
                'success' => false,
                'error' => "Badge '{$badge->name}' is not active",
                'error_code' => error_codes::BADGE_NOT_ACTIVE,
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

        // Check if already awarded (Moodle-level idempotency).
        // Query badge_issued directly instead of calling is_issued() + get_record (avoids duplicate query).
        $issued = $DB->get_record('badge_issued', [
            'badgeid' => $badgeid,
            'userid' => $userid,
        ]);
        if ($issued) {
            return [
                'success' => true,
                'result' => [
                    'status' => 'already_awarded',
                    'badge_name' => $badge->name,
                    'uniquehash' => $issued->uniquehash,
                    'issued_at' => $issued->dateissued,
                ],
            ];
        }

        // Issue the badge.
        $badge->issue($userid);

        // Get the issued record for the hash.
        $issued = $DB->get_record('badge_issued', [
            'badgeid' => $badgeid,
            'userid' => $userid,
        ]);

        if (!$issued) {
            return [
                'success' => false,
                'error' => "Badge issue() was called but no badge_issued record found for badge {$badgeid}, user {$userid}",
                'error_code' => error_codes::ACTION_FAILED,
                'retry' => true,
            ];
        }

        return [
            'success' => true,
            'result' => [
                'status' => 'awarded',
                'badge_name' => $badge->name,
                'uniquehash' => $issued->uniquehash,
                'issued_at' => $issued->dateissued,
            ],
        ];
    }
}
