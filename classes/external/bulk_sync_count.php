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
 * External function for bulk sync preflight (user count and quota check).
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
 * External function for bulk sync preflight.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_sync_count extends external_api {
    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Count active users and check if user_updated is monitored.
     *
     * @return array Preflight data
     */
    public static function execute(): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), []);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $sitekey = get_config('local_mc_plugin', 'site_key');
        if (empty($sitekey)) {
            return [
                'success' => false,
                'message' => get_string('bulk_sync_no_connection', 'local_mc_plugin'),
                'count' => 0,
                'monitored' => false,
                'quota_used' => 0,
                'quota_limit' => 0,
            ];
        }

        // Check if user_updated is in monitored events.
        $monitoredeventsstr = get_config('local_mc_plugin', 'monitored_events');
        $monitoredevents = array_map(function ($e) {
            return ltrim(trim($e), '\\');
        }, explode(',', $monitoredeventsstr));
        $monitored = in_array(\core\event\user_updated::class, $monitoredevents);

        $count = $DB->count_records_select('user', 'deleted = 0 AND suspended = 0 AND id > 1');

        // Get quota usage data.
        $usagejson = get_config('local_mc_plugin', 'events_limit_usage');
        $usage = $usagejson ? json_decode($usagejson, true) : null;
        $quotaused = 0;
        $quotalimit = 0;
        if (is_array($usage)) {
            $quotaused = (int)($usage['current'] ?? 0);
            $quotalimit = (int)($usage['limit'] ?? 0);
        }

        return [
            'success' => true,
            'message' => '',
            'count' => (int)$count,
            'monitored' => $monitored,
            'quota_used' => $quotaused,
            'quota_limit' => $quotalimit,
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the check succeeded'),
            'message' => new external_value(PARAM_RAW, 'Error message if failed'),
            'count' => new external_value(PARAM_INT, 'Number of active users'),
            'monitored' => new external_value(PARAM_BOOL, 'Whether user_updated is monitored'),
            'quota_used' => new external_value(PARAM_INT, 'Current quota usage'),
            'quota_limit' => new external_value(PARAM_INT, 'Quota limit (0 = no quota)'),
        ]);
    }
}
