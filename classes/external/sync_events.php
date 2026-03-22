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
 * External function to sync monitored event schemas to MoodleConnect.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\external;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function to sync monitored event schemas to MoodleConnect.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_events extends external_api {
    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Sync monitored event schemas to MoodleConnect.
     *
     * @return array Result with success, message, event_count, course_sync
     */
    public static function execute(): array {
        $params = self::validate_parameters(self::execute_parameters(), []);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $sitekey = get_config('local_mc_plugin', 'site_key');
        if (empty($sitekey)) {
            return [
                'success' => false,
                'message' => get_string('error_no_site_key', 'local_mc_plugin'),
                'event_count' => 0,
                'course_sync_success' => false,
                'course_sync_message' => '',
            ];
        }

        $monitoredevents = get_config('local_mc_plugin', 'monitored_events');
        $eventclasses = array_filter(array_map('trim', explode(',', $monitoredevents)));

        $schemas = [];
        if (!empty($eventclasses)) {
            $inspector = new \local_mc_plugin\local\dynamic_inspector();
            $schemas = $inspector->get_event_schemas($eventclasses);
        }

        $result = \local_mc_plugin\local\moodleconnect_client::sync_schema($schemas);

        $coursesyncsuccess = false;
        $coursesyncmessage = '';
        if ($result['success']) {
            try {
                $courseresult = \local_mc_plugin\local\moodleconnect_client::sync_all_courses();
                $coursesyncsuccess = $courseresult['success'];
                $coursesyncmessage = $courseresult['message'] ?? '';
            } catch (\Exception $e) {
                $coursesyncmessage = $e->getMessage();
            }
        }

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'event_count' => count($schemas),
            'course_sync_success' => $coursesyncsuccess,
            'course_sync_message' => $coursesyncmessage,
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether sync succeeded'),
            'message' => new external_value(PARAM_RAW, 'Status message'),
            'event_count' => new external_value(PARAM_INT, 'Number of synced events'),
            'course_sync_success' => new external_value(PARAM_BOOL, 'Whether course sync succeeded'),
            'course_sync_message' => new external_value(PARAM_RAW, 'Course sync message'),
        ]);
    }
}
