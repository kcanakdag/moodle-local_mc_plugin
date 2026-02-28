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
 * Handler for adding users to course groups.
 *
 * Uses Moodle's core groups_add_member() function from group/lib.php.
 * Verifies the user is enrolled in the course before adding to the group.
 * This action is always available as groups are a core Moodle feature.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\actions;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Action handler for adding users to groups.
 *
 * Checks groups_members for duplicates and verifies course enrolment
 * before adding. Returns the group name on success.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_to_group_handler implements action_handler {
    /**
     * Get the action type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'add_to_group';
    }

    /**
     * Check availability — groups are always available in Moodle core.
     *
     * @return array ['available' => bool, 'error' => string|null]
     */
    public function check_availability(): array {
        return ['available' => true, 'error' => null];
    }

    /**
     * Add a user to a course group.
     *
     * @param object $data Action data with action_config and event_payload.
     * @return array Structured result.
     */
    public function execute(object $data): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $groupid = $data->action_config->group_id;

        // Get group.
        $group = $DB->get_record('groups', ['id' => $groupid]);
        if (!$group) {
            return [
                'success' => false,
                'error' => "Group ID {$groupid} not found",
                'error_code' => 'group_not_found',
                'retry' => false,
            ];
        }

        // Get user from event payload.
        $userid = $data->event_payload->user->id ?? null;
        if (!$userid) {
            return [
                'success' => false,
                'error' => 'User ID not found in event payload',
                'error_code' => 'invalid_payload',
                'retry' => false,
            ];
        }

        // Check if user is enrolled in the course.
        $context = \context_course::instance($group->courseid);
        if (!is_enrolled($context, $userid)) {
            return [
                'success' => false,
                'error' => 'User is not enrolled in the course containing this group',
                'error_code' => 'user_not_enrolled',
                'retry' => false,
            ];
        }

        // Check if already a member.
        if (groups_is_member($groupid, $userid)) {
            return [
                'success' => true,
                'result' => [
                    'status' => 'already_member',
                    'group_name' => $group->name,
                ],
            ];
        }

        // Add to group.
        groups_add_member($groupid, $userid);

        return [
            'success' => true,
            'result' => [
                'status' => 'added',
                'group_name' => $group->name,
            ],
        ];
    }
}
