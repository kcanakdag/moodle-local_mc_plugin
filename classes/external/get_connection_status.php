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
 * External function to check connection status with MoodleConnect.
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
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * External function to check connection status with MoodleConnect.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_connection_status extends external_api {
    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Check connection status by calling MoodleConnect API.
     *
     * @return array Connection status data
     */
    public static function execute(): array {
        global $CFG;

        require_once($CFG->dirroot . '/local/mc_plugin/lib.php');
        require_once($CFG->libdir . '/filelib.php');

        $params = self::validate_parameters(self::execute_parameters(), []);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $sitekey = get_config('local_mc_plugin', 'site_key');

        if (empty($sitekey)) {
            return [
                'configured' => false,
                'connected' => false,
                'site_name' => '',
                'synced_event_count' => 0,
                'synced_events' => [],
                'error' => '',
            ];
        }

        $baseurl = local_mc_plugin_get_api_url();
        $statusurl = $baseurl . '/sites/status?site_key=' . urlencode($sitekey) . '&activate=true';

        $curloptions = ['proxy' => true];
        $parsedurl = parse_url($baseurl);
        $host = $parsedurl['host'] ?? '';
        $isprivateip = filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        if ($isprivateip) {
            $curloptions['ignoresecurity'] = true;
        }

        $curl = new \curl($curloptions);
        $curl->setopt(['timeout' => 5]);
        $result = $curl->get($statusurl);
        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if ($httpcode === 200 && $result) {
            $data = json_decode($result, true);
            return [
                'configured' => true,
                'connected' => true,
                'site_name' => $data['site_name'] ?? '',
                'synced_event_count' => (int)($data['synced_event_count'] ?? 0),
                'synced_events' => $data['synced_events'] ?? [],
                'error' => '',
            ];
        }

        $curlerror = $curl->get_errno() ? $curl->error : '';
        return [
            'configured' => true,
            'connected' => false,
            'site_name' => '',
            'synced_event_count' => 0,
            'synced_events' => [],
            'error' => 'Cannot reach MoodleConnect',
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'configured' => new external_value(PARAM_BOOL, 'Whether site key is configured'),
            'connected' => new external_value(PARAM_BOOL, 'Whether connection is active'),
            'site_name' => new external_value(PARAM_RAW, 'Connected site name'),
            'synced_event_count' => new external_value(PARAM_INT, 'Number of synced events'),
            'synced_events' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Event class name'),
                'List of synced event class names',
                VALUE_OPTIONAL
            ),
            'error' => new external_value(PARAM_RAW, 'Error message if not connected'),
        ]);
    }
}
