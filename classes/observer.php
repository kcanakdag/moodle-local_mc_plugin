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

use local_mc_plugin\local\moodleconnect_client;
use local_mc_plugin\local\dynamic_inspector;

/**
 * Event observer class for capturing and forwarding Moodle events to MoodleConnect.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Get the course filter configuration for a specific event type.
     *
     * @param string $eventname The event name (e.g., \core\event\user_created)
     * @return array|null The course filter config or null if no filter
     */
    private static function get_course_filter_for_event(string $eventname): ?array {
        $configjson = get_config('local_mc_plugin', 'monitored_events_config');

        if (empty($configjson)) {
            return null;
        }

        $eventsconfig = json_decode($configjson, true);
        if (!is_array($eventsconfig)) {
            return null;
        }

        // Normalize event name for comparison.
        $eventnamenormalized = ltrim($eventname, '\\');

        foreach ($eventsconfig as $eventconfig) {
            if (!isset($eventconfig['event_type'])) {
                continue;
            }

            $configeventnormalized = ltrim($eventconfig['event_type'], '\\');

            if ($configeventnormalized === $eventnamenormalized) {
                return $eventconfig['course_filter'] ?? null;
            }
        }

        return null;
    }

    /**
     * Safely extract the course ID from an event.
     *
     * This method tries multiple approaches to get the course ID:
     * 1. Direct courseid property on the event
     * 2. Context-based course ID
     *
     * Returns null if no course context can be determined (event should be sent).
     *
     * @param \core\event\base $event The Moodle event
     * @return int|null The course ID or null if not determinable
     */
    private static function get_event_course_id(\core\event\base $event): ?int {
        // Try direct courseid property first.
        try {
            $courseid = $event->courseid;
            if (!empty($courseid) && is_numeric($courseid) && (int)$courseid > 0) {
                return (int)$courseid;
            }
        } catch (\Exception $e) {
            // Property may not exist or be accessible.
            unset($e);
        }

        // Try getting course ID from context.
        try {
            $context = $event->get_context();
            if ($context) {
                // For course context, get the instance ID directly.
                if ($context->contextlevel === CONTEXT_COURSE) {
                    return (int)$context->instanceid;
                }

                // For module context, get the course from the context path.
                if ($context->contextlevel === CONTEXT_MODULE) {
                    $coursecontext = $context->get_course_context(false);
                    if ($coursecontext) {
                        return (int)$coursecontext->instanceid;
                    }
                }
            }
        } catch (\Exception $e) {
            // Context may not be available.
            unset($e);
        }

        // Could not determine course ID - return null (event will be sent).
        return null;
    }

    /**
     * Check if an event passes the course filter.
     *
     * Philosophy: When in doubt, send the event. Backend will filter again.
     * - If no filter configured: send
     * - If can't determine course ID: send
     * - If course ID is site-level (0 or 1): send
     * - Otherwise: apply filter logic
     *
     * @param \core\event\base $event The Moodle event
     * @param array|null $coursefilter The course filter config
     * @return bool True if event should be sent
     */
    private static function passes_course_filter(\core\event\base $event, ?array $coursefilter): bool {
        // No filter = send all.
        if ($coursefilter === null) {
            return true;
        }

        // Invalid filter structure = send (fail open).
        if (!isset($coursefilter['mode']) || !isset($coursefilter['course_ids'])) {
            return true;
        }

        $mode = $coursefilter['mode'];
        $filterids = $coursefilter['course_ids'];

        // Empty filter IDs = send all.
        if (empty($filterids) || !is_array($filterids)) {
            return true;
        }

        // Get course ID from event.
        $courseid = self::get_event_course_id($event);

        // Can't determine course ID = send (fail open).
        // This handles events without course context (user_created, etc.).
        if ($courseid === null) {
            return true;
        }

        // Site-level events (courseid 0 or 1) = send.
        // SITEID constant is typically 1.
        if ($courseid <= 1) {
            return true;
        }

        // Apply filter logic.
        if ($mode === 'include') {
            return in_array($courseid, $filterids, true);
        } else if ($mode === 'exclude') {
            return !in_array($courseid, $filterids, true);
        }

        // Unknown mode = send (fail open).
        return true;
    }

    /**
     * Check if events are currently blocked due to limit exceeded.
     *
     * MoodleConnect pushes a blocked_until timestamp when the user exceeds
     * their monthly event quota. Events are blocked until that timestamp passes.
     *
     * @return bool True if events are blocked, false if they can be sent
     */
    private static function is_events_blocked(): bool {
        $blockeduntil = (int) get_config('local_mc_plugin', 'events_blocked_until');

        // No block set or timestamp is 0.
        if ($blockeduntil <= 0) {
            return false;
        }

        // Check if we're still within the blocked period.
        return time() < $blockeduntil;
    }

    /**
     * Generic handler for ALL events.
     *
     * This method is called for every event that occurs in Moodle. It checks if the event
     * is in the monitored events list, applies course filtering, extracts the event data,
     * and sends it to MoodleConnect. In debug mode, it also logs events to a file and
     * displays notifications.
     *
     * @param \core\event\base $event The Moodle event object
     * @return void
     */
    public static function handle_event(\core\event\base $event) {
        global $CFG;

        // Check if events are blocked due to limit exceeded.
        if (self::is_events_blocked()) {
            // Log blocked event in debug mode.
            if (get_config('local_mc_plugin', 'debug_mode')) {
                $logfile = $CFG->dataroot . '/moodleconnect_debug.log';
                $blockeduntil = (int) get_config('local_mc_plugin', 'events_blocked_until');
                $msg = date('Y-m-d H:i:s') . " | Event blocked (limit exceeded): {$event->eventname}";
                $msg .= " (blocked until " . date('Y-m-d H:i:s', $blockeduntil) . ")\n";
                @file_put_contents($logfile, $msg, FILE_APPEND);
            }
            return;
        }

        // Debug: log ALL events to file if debug mode is on.
        if (get_config('local_mc_plugin', 'debug_mode')) {
            $logfile = $CFG->dataroot . '/moodleconnect_debug.log';
            $msg = date('Y-m-d H:i:s') . " | Event received: {$event->eventname}\n";
            @file_put_contents($logfile, $msg, FILE_APPEND);
        }

        // Get monitored events (comma separated string from multiselect).
        $monitoredeventsstr = get_config('local_mc_plugin', 'monitored_events');
        $monitoredevents = array_map('trim', explode(',', $monitoredeventsstr));

        // Normalize event name - remove leading backslash for comparison.
        // Event names from Moodle have leading \, but stored config may not.
        $eventnamenormalized = ltrim($event->eventname, '\\');
        $monitorednormalized = array_map(function ($e) {
            return ltrim($e, '\\');
        }, $monitoredevents);

        // Allow wildcard (for debugging) or check exact match.
        if ($monitoredeventsstr !== '*' && !in_array($eventnamenormalized, $monitorednormalized)) {
            return;
        }

        // Check course filter before sending.
        $coursefilter = self::get_course_filter_for_event($event->eventname);
        if (!self::passes_course_filter($event, $coursefilter)) {
            // Event filtered out by course filter.
            if (get_config('local_mc_plugin', 'debug_mode')) {
                $logfile = $CFG->dataroot . '/moodleconnect_debug.log';
                $courseid = self::get_event_course_id($event);
                $msg = date('Y-m-d H:i:s') . " | Event filtered by course filter: {$event->eventname}";
                $msg .= " (courseid={$courseid}, filter=" . json_encode($coursefilter) . ")\n";
                @file_put_contents($logfile, $msg, FILE_APPEND);
            }
            return;
        }

        // Extract Data using the rich inspector.
        $inspector = new dynamic_inspector();
        $payload = $inspector->extract_data($event);

        // Send to MoodleConnect.
        $result = moodleconnect_client::send_event($event->eventname, $payload);

        // Debug mode: show notification and console log.
        if (get_config('local_mc_plugin', 'debug_mode')) {
            global $PAGE;

            // Add console.log via inline JS.
            $logdata = json_encode([
                'event' => $event->eventname,
                'success' => $result['success'],
                'message' => $result['message'],
                'timestamp' => date('c'),
            ]);

            try {
                $PAGE->requires->js_amd_inline("
                    console.log('%c[MoodleConnect]', 'color: #4CAF50; font-weight: bold', {$logdata});
                ");
            } catch (\Exception $e) {
                // May fail if PAGE not ready.
                unset($e);
            }

            // Also show notification.
            try {
                if ($result['success']) {
                    \core\notification::success(
                        get_string('success_event_sent', 'local_mc_plugin', $event->eventname)
                    );
                } else {
                    \core\notification::error(
                        get_string('failed_event_sent', 'local_mc_plugin', [
                            'event' => $event->eventname,
                            'message' => $result['message'],
                        ])
                    );
                }
            } catch (\Exception $e) {
                // Notification may fail in some contexts, ignore.
                unset($e);
            }
        }
    }
}
