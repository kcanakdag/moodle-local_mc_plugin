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
 * External function to save plugin settings.
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
 * External function to save plugin settings.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_settings extends external_api {
    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'site_key' => new external_value(PARAM_RAW, 'Site key', VALUE_DEFAULT, ''),
            'site_secret' => new external_value(PARAM_RAW, 'Site secret', VALUE_DEFAULT, ''),
            'monitored_events' => new external_value(PARAM_RAW, 'Comma-separated event classes', VALUE_DEFAULT, ''),
            'debug_mode' => new external_value(PARAM_INT, 'Debug mode flag', VALUE_DEFAULT, -1),
        ]);
    }

    /**
     * Save plugin settings.
     *
     * @param string $sitekey Site key
     * @param string $sitesecret Site secret
     * @param string $monitoredevents Comma-separated event classes
     * @param int $debugmode Debug mode (-1 means not provided)
     * @return array Result with success, message, saved fields, course_sync
     */
    public static function execute(
        string $sitekey = '',
        string $sitesecret = '',
        string $monitoredevents = '',
        int $debugmode = -1
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'site_key' => $sitekey,
            'site_secret' => $sitesecret,
            'monitored_events' => $monitoredevents,
            'debug_mode' => $debugmode,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $saved = [];
        $credentialssaved = false;

        // Validate and save site_key.
        if ($params['site_key'] !== '') {
            $keylen = strlen($params['site_key']);
            if ($keylen < 16 || $keylen > 128) {
                return self::error_response(get_string('error_invalid_site_key_length', 'local_mc_plugin'));
            }
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $params['site_key'])) {
                return self::error_response(get_string('error_invalid_site_key_format', 'local_mc_plugin'));
            }
            set_config('site_key', $params['site_key'], 'local_mc_plugin');
            $saved[] = 'site_key';
        }

        // Validate and save site_secret.
        if ($params['site_secret'] !== '') {
            $secretlen = strlen($params['site_secret']);
            if ($secretlen < 16 || $secretlen > 128) {
                return self::error_response(get_string('error_invalid_site_secret_length', 'local_mc_plugin'));
            }
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $params['site_secret'])) {
                return self::error_response(get_string('error_invalid_site_secret_format', 'local_mc_plugin'));
            }
            set_config('site_secret', $params['site_secret'], 'local_mc_plugin');
            $saved[] = 'site_secret';
        }

        if ($params['site_key'] !== '' && $params['site_secret'] !== '') {
            $credentialssaved = true;
        }

        // Validate and save monitored_events.
        if ($params['monitored_events'] !== '') {
            $trimmed = trim($params['monitored_events']);
            if (!empty($trimmed)) {
                $classes = array_filter(array_map('trim', explode(',', $trimmed)));
                foreach ($classes as $class) {
                    $class = ltrim($class, '\\');
                    $fullclass = '\\' . $class;
                    if (!class_exists($fullclass)) {
                        $msg = get_string('error_invalid_event_class', 'local_mc_plugin', $class);
                        return self::error_response($msg);
                    }
                    if (!is_subclass_of($fullclass, '\\core\\event\\base')) {
                        $msg = get_string('error_invalid_event_class_not_event', 'local_mc_plugin', $class);
                        return self::error_response($msg);
                    }
                }
            }
            set_config('monitored_events', $params['monitored_events'], 'local_mc_plugin');
            $saved[] = 'monitored_events';
        }

        // Save debug_mode (-1 means not provided).
        if ($params['debug_mode'] >= 0) {
            set_config('debug_mode', $params['debug_mode'], 'local_mc_plugin');
            $saved[] = 'debug_mode';
        }

        // Trigger course sync if credentials were saved.
        $coursesyncsuccess = false;
        $coursesyncmessage = '';
        if ($credentialssaved) {
            try {
                $result = \local_mc_plugin\local\moodleconnect_client::sync_all_courses();
                $coursesyncsuccess = $result['success'];
                $coursesyncmessage = $result['message'] ?? '';
            } catch (\Exception $e) {
                $coursesyncmessage = $e->getMessage();
            }
        }

        return [
            'success' => true,
            'message' => get_string('success_settings_saved', 'local_mc_plugin'),
            'saved' => implode(',', $saved),
            'course_sync_success' => $coursesyncsuccess,
            'course_sync_message' => $coursesyncmessage,
        ];
    }

    /**
     * Build a standard error response.
     *
     * @param string $message Error message
     * @return array
     */
    private static function error_response(string $message): array {
        return [
            'success' => false,
            'message' => $message,
            'saved' => '',
            'course_sync_success' => false,
            'course_sync_message' => '',
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the save succeeded'),
            'message' => new external_value(PARAM_RAW, 'Status message'),
            'saved' => new external_value(PARAM_RAW, 'Comma-separated list of saved fields'),
            'course_sync_success' => new external_value(PARAM_BOOL, 'Whether course sync succeeded'),
            'course_sync_message' => new external_value(PARAM_RAW, 'Course sync message'),
        ]);
    }
}
