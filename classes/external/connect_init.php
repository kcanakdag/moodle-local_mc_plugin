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
 * External function to initialize the MoodleConnect connection flow.
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
 * External function to initialize the MoodleConnect connection flow.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connect_init extends external_api {
    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Initialize the connection flow by requesting a token from MoodleConnect.
     *
     * @return array Result with success, token/message, expires_at
     */
    public static function execute(): array {
        global $CFG, $SITE, $USER;

        require_once($CFG->dirroot . '/local/mc_plugin/lib.php');
        require_once($CFG->libdir . '/filelib.php');

        $params = self::validate_parameters(self::execute_parameters(), []);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        // Rate limiting: max 5 connection attempts per minute per user.
        $cachekey = 'connect_attempts_' . $USER->id;
        $cache = \cache::make('local_mc_plugin', 'mc_metadata');
        $attempts = $cache->get($cachekey);

        if ($attempts === false) {
            $attempts = ['count' => 0, 'reset_time' => time() + 60];
        }

        if (time() > $attempts['reset_time']) {
            $attempts = ['count' => 0, 'reset_time' => time() + 60];
        }

        if ($attempts['count'] >= 5) {
            return [
                'success' => false,
                'token' => '',
                'expires_at' => '',
                'message' => get_string('error_rate_limit', 'local_mc_plugin'),
            ];
        }

        $attempts['count']++;
        $cache->set($cachekey, $attempts);

        // Call MoodleConnect API.
        $baseurl = local_mc_plugin_get_api_url();
        $initurl = $baseurl . '/connect/init';

        $payload = json_encode([
            'moodle_url' => $CFG->wwwroot,
            'moodle_site_name' => $SITE->fullname,
            'plugin_version' => get_config('local_mc_plugin', 'version'),
        ]);

        $curloptions = ['proxy' => true];
        $parsedurl = parse_url($initurl);
        $host = $parsedurl['host'] ?? '';
        $isprivateip = filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        if ($isprivateip) {
            $curloptions['ignoresecurity'] = true;
        }
        $curl = new \curl($curloptions);
        $curl->setopt(['timeout' => 15, 'connecttimeout' => 10]);
        $curl->setHeader(['Content-Type: application/json', 'Content-Length: ' . strlen($payload)]);

        $result = $curl->post($initurl, $payload);

        if ($curl->get_errno()) {
            $curlerror = $curl->error;
            return [
                'success' => false,
                'token' => '',
                'expires_at' => '',
                'message' => get_string('error_connection_failed', 'local_mc_plugin', $curlerror),
            ];
        }

        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;
        $response = json_decode($result, true);

        if ($httpcode >= 200 && $httpcode < 300 && isset($response['token'])) {
            return [
                'success' => true,
                'token' => $response['token'],
                'expires_at' => $response['expires_at'] ?? '',
                'message' => '',
            ];
        }

        $errormessage = $response['error'] ?? $response['message'] ?? "HTTP $httpcode";
        return [
            'success' => false,
            'token' => '',
            'expires_at' => '',
            'message' => $errormessage,
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request succeeded'),
            'token' => new external_value(PARAM_RAW, 'Connection token'),
            'expires_at' => new external_value(PARAM_RAW, 'Token expiry timestamp'),
            'message' => new external_value(PARAM_RAW, 'Error message if failed'),
        ]);
    }
}
