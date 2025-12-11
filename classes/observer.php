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
 * Event observer for MoodleConnect plugin.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin;

defined('MOODLE_INTERNAL') || die();

use local_mc_plugin\local\moodleconnect_client;
use local_mc_plugin\local\dynamic_inspector;

/**
 * Event observer class for capturing and forwarding Moodle events to MoodleConnect.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer
{
    /**
     * Generic handler for ALL events.
     *
     * This method is called for every event that occurs in Moodle. It checks if the event
     * is in the monitored events list, extracts the event data, and sends it to MoodleConnect.
     * In debug mode, it also logs events to a file and displays notifications.
     *
     * @param \core\event\base $event The Moodle event object
     * @return void
     */
    public static function handle_event(\core\event\base $event)
    {
        global $CFG;

        // Debug: log ALL events to file if debug mode is on
        if (get_config('local_mc_plugin', 'debug_mode')) {
            $logfile = $CFG->dataroot . '/moodleconnect_debug.log';
            $msg = date('Y-m-d H:i:s') . " | Event received: {$event->eventname}\n";
            @file_put_contents($logfile, $msg, FILE_APPEND);
        }

        // Get monitored events (comma separated string from multiselect)
        $monitored_events_str = get_config('local_mc_plugin', 'monitored_events');
        $monitored_events = array_map('trim', explode(',', $monitored_events_str));

        // Normalize event name - remove leading backslash for comparison
        // Event names from Moodle have leading \, but stored config may not
        $eventname_normalized = ltrim($event->eventname, '\\');
        $monitored_normalized = array_map(function ($e) {
            return ltrim($e, '\\');
        }, $monitored_events);

        // Allow wildcard (for debugging) or check exact match
        if ($monitored_events_str !== '*' && !in_array($eventname_normalized, $monitored_normalized)) {
            return;
        }

        // Extract Data using the rich inspector
        $inspector = new dynamic_inspector();
        $payload = $inspector->extract_data($event);

        // Send to MoodleConnect
        $result = moodleconnect_client::send_event($event->eventname, $payload);

        // Debug mode: show notification and console log
        if (get_config('local_mc_plugin', 'debug_mode')) {
            global $PAGE;

            // Add console.log via inline JS
            $log_data = json_encode([
                'event' => $event->eventname,
                'success' => $result['success'],
                'message' => $result['message'],
                'timestamp' => date('c')
            ]);

            try {
                $PAGE->requires->js_amd_inline("
                    console.log('%c[MoodleConnect]', 'color: #4CAF50; font-weight: bold', {$log_data});
                ");
            } catch (\Exception $e) {
                // May fail if PAGE not ready
            }

            // Also show notification
            try {
                if ($result['success']) {
                    \core\notification::success(
                        get_string('success_event_sent', 'local_mc_plugin', $event->eventname)
                    );
                } else {
                    \core\notification::error(
                        get_string('failed_event_sent', 'local_mc_plugin', [
                            'event' => $event->eventname,
                            'message' => $result['message']
                        ])
                    );
                }
            } catch (\Exception $e) {
                // Notification may fail in some contexts, ignore
            }
        }
    }
}
