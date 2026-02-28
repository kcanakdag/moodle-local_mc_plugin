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
 * Handler for adding users to cohorts.
 *
 * Uses Moodle's core cohort_add_member() function from cohort/lib.php.
 * Checks cohort_members for duplicates before adding.
 * This action is always available as cohorts are a core Moodle feature.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\actions;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Action handler for adding users to cohorts.
 *
 * Checks cohort_members for duplicates before adding. Returns the
 * cohort name on success.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_to_cohort_handler implements action_handler {
    /**
     * Get the action type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'add_to_cohort';
    }

    /**
     * Check availability — cohorts are always available in Moodle core.
     *
     * @return array ['available' => bool, 'error' => string|null]
     */
    public function check_availability(): array {
        return ['available' => true, 'error' => null];
    }

    /**
     * Add a user to a cohort.
     *
     * @param object $data Action data with action_config and event_payload.
     * @return array Structured result.
     */
    public function execute(object $data): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $cohortid = $data->action_config->cohort_id;

        // Get cohort.
        $cohort = $DB->get_record('cohort', ['id' => $cohortid]);
        if (!$cohort) {
            return [
                'success' => false,
                'error' => "Cohort ID {$cohortid} not found",
                'error_code' => 'cohort_not_found',
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

        // Check if already a member.
        if (cohort_is_member($cohortid, $userid)) {
            return [
                'success' => true,
                'result' => [
                    'status' => 'already_member',
                    'cohort_name' => $cohort->name,
                ],
            ];
        }

        // Add to cohort.
        cohort_add_member($cohortid, $userid);

        return [
            'success' => true,
            'result' => [
                'status' => 'added',
                'cohort_name' => $cohort->name,
            ],
        ];
    }
}
