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
 * Handler for enrolling users in courses.
 *
 * Uses Moodle's manual enrolment plugin via enrol_get_plugin('manual')->enrol_user().
 * Requires the manual enrolment plugin to be enabled for the target course.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\actions;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Action handler for enrolling users.
 *
 * Checks existing enrolments for duplicates before enrolling. Returns
 * the enrolment ID and role name on success.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enroll_user_handler implements action_handler {
    /**
     * Get the action type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'enroll_user';
    }

    /**
     * Check if the manual enrolment plugin is available.
     *
     * @return array ['available' => bool, 'error' => string|null]
     */
    public function check_availability(): array {
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            return [
                'available' => false,
                'error' => 'Manual enrolment plugin is not installed',
            ];
        }
        return ['available' => true, 'error' => null];
    }

    /**
     * Enrol a user in a course with the specified role.
     *
     * @param object $data Action data with action_config and event_payload.
     * @return array Structured result.
     */
    public function execute(object $data): array {
        global $DB;

        $courseid = $data->action_config->course_id;
        $roleid = isset($data->action_config->role_id) ? (int) $data->action_config->role_id : null;

        // Get course.
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return [
                'success' => false,
                'error' => "Course ID {$courseid} not found",
                'error_code' => error_codes::COURSE_NOT_FOUND,
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

        // Get manual enrolment instance for this course.
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            return [
                'success' => false,
                'error' => 'Manual enrolment plugin is not installed or enabled',
                'error_code' => error_codes::MANUAL_ENROL_PLUGIN_MISSING,
                'retry' => false,
            ];
        }

        $instances = $DB->get_records('enrol', ['courseid' => $courseid, 'enrol' => 'manual']);
        $instance = reset($instances);

        if (!$instance) {
            return [
                'success' => false,
                'error' => "Manual enrolment is not enabled for course '{$course->fullname}'. "
                    . "Go to Course > Participants > Enrolment methods and enable Manual enrolments.",
                'error_code' => error_codes::MANUAL_ENROL_NOT_ENABLED,
                'retry' => false,
            ];
        }

        // Resolve role safely. If omitted, fall back to the site's student archetype.
        if (!$roleid) {
            $studentrole = $DB->get_record('role', ['archetype' => 'student'], 'id');
            if (!$studentrole) {
                return [
                    'success' => false,
                    'error' => 'role_id is required and no default student role was found',
                    'error_code' => error_codes::ROLE_NOT_FOUND,
                    'retry' => false,
                ];
            }
            $roleid = (int) $studentrole->id;
        }

        $role = $DB->get_record('role', ['id' => $roleid]);
        if (!$role) {
            return [
                'success' => false,
                'error' => "Role ID {$roleid} not found",
                'error_code' => error_codes::ROLE_NOT_FOUND,
                'retry' => false,
            ];
        }

        // Check if already enrolled with same role.
        $context = \context_course::instance($courseid);
        if (is_enrolled($context, $userid)) {
            $assignedroles = get_user_roles($context, $userid);
            foreach ($assignedroles as $assignedrole) {
                if ($assignedrole->roleid == $roleid) {
                    return [
                        'success' => true,
                        'result' => [
                            'status' => 'already_enrolled',
                            'course_name' => $course->fullname,
                            'role_id' => $roleid,
                        ],
                    ];
                }
            }
        }

        // Enrol the user.
        $plugin->enrol_user($instance, $userid, $roleid);

        // Get the enrolment record.
        $enrolment = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $userid,
        ]);

        if (!$enrolment) {
            return [
                'success' => false,
                'error' => "enrol_user() was called but no enrolment record found for user {$userid} in course {$courseid}",
                'error_code' => error_codes::ACTION_FAILED,
                'retry' => true,
            ];
        }

        return [
            'success' => true,
            'result' => [
                'status' => 'enrolled',
                'enrolment_id' => $enrolment->id,
                'course_name' => $course->fullname,
                'role_id' => $roleid,
                'role_name' => $role->shortname,
            ],
        ];
    }
}
