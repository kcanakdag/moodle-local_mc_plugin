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
 * Handler for suspending or unsuspending user enrolments.
 *
 * Uses Moodle's enrolment plugin update_user_enrol() method to toggle
 * enrolment status between ENROL_USER_SUSPENDED and ENROL_USER_ACTIVE.
 * This action is always available as it uses core enrolment API.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\actions;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Action handler for suspending/unsuspending enrolments.
 *
 * Checks current enrolment status before updating. Returns the
 * enrolment ID and new status on success.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suspend_enrolment_handler implements action_handler {
    /**
     * Get the action type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'suspend_enrolment';
    }

    /**
     * Check availability — enrolment API is always available in Moodle core.
     *
     * @return array ['available' => bool, 'error' => string|null]
     */
    public function check_availability(): array {
        return ['available' => true, 'error' => null];
    }

    /**
     * Suspend or unsuspend a user's enrolment in a course.
     *
     * @param object $data Action data with action_config and event_payload.
     * @return array Structured result.
     */
    public function execute(object $data): array {
        global $DB;

        $courseid = $data->action_config->course_id;
        $suspend = $data->action_config->suspend ?? true;

        // Get course.
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return [
                'success' => false,
                'error' => "Course ID {$courseid} not found",
                'error_code' => 'course_not_found',
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

        // Check if user is enrolled.
        $context = \context_course::instance($courseid);
        if (!is_enrolled($context, $userid)) {
            return [
                'success' => false,
                'error' => "User is not enrolled in course '{$course->fullname}'",
                'error_code' => 'user_not_enrolled',
                'retry' => false,
            ];
        }

        // Get user's enrolment record.
        $sql = "SELECT ue.*, e.enrol
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid AND ue.userid = :userid";
        $enrolments = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

        if (empty($enrolments)) {
            return [
                'success' => false,
                'error' => 'No enrolment record found',
                'error_code' => 'enrolment_not_found',
                'retry' => false,
            ];
        }

        $newstatus = $suspend ? ENROL_USER_SUSPENDED : ENROL_USER_ACTIVE;
        $statusname = $suspend ? 'suspended' : 'active';

        $updatedenrolmentids = [];
        foreach ($enrolments as $enrolment) {
            // Already at target status for this enrolment record.
            if ((int) $enrolment->status === $newstatus) {
                continue;
            }

            $plugin = enrol_get_plugin($enrolment->enrol);
            if (!$plugin) {
                return [
                    'success' => false,
                    'error' => "Enrolment plugin '{$enrolment->enrol}' not found",
                    'error_code' => 'enrolment_plugin_missing',
                    'retry' => false,
                ];
            }

            $instance = $DB->get_record('enrol', ['id' => $enrolment->enrolid]);
            if (!$instance) {
                return [
                    'success' => false,
                    'error' => "Enrolment instance {$enrolment->enrolid} not found",
                    'error_code' => 'enrolment_not_found',
                    'retry' => false,
                ];
            }

            $plugin->update_user_enrol($instance, $userid, $newstatus);
            $updatedenrolmentids[] = (int) $enrolment->id;
        }

        if (empty($updatedenrolmentids)) {
            return [
                'success' => true,
                'result' => [
                    'status' => $suspend ? 'already_suspended' : 'already_active',
                    'course_name' => $course->fullname,
                    'enrolment_ids' => array_map(static function ($e) {
                        return (int) $e->id;
                    }, $enrolments),
                    // Kept for backward compatibility with older consumers.
                    'enrolment_id' => (int) reset($enrolments)->id,
                ],
            ];
        }

        return [
            'success' => true,
            'result' => [
                'status' => $statusname,
                'course_name' => $course->fullname,
                'enrolment_id' => $updatedenrolmentids[0],
                'enrolment_ids' => $updatedenrolmentids,
                'updated_count' => count($updatedenrolmentids),
                'new_status' => $statusname,
            ],
        ];
    }
}
