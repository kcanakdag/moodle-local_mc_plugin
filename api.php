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
 * API endpoint for MoodleConnect to communicate with the plugin.
 *
 * This endpoint allows MoodleConnect to:
 * - Update monitored events (reverse sync when triggers change)
 * - Request event schema sync
 *
 * Security: All requests are authenticated via HMAC signature using site_secret.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $CFG;

header('Content-Type: application/json');

// Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON body.
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Extract required fields.
$action = $data['action'] ?? '';
$sitekey = $data['site_key'] ?? '';
$signature = $data['signature'] ?? '';
$timestamp = $data['timestamp'] ?? 0;

if (empty($action) || empty($sitekey) || empty($signature) || empty($timestamp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Verify site_key matches our configured key.
$configuredsitekey = get_config('local_mc_plugin', 'site_key');
$configuredsitesecret = get_config('local_mc_plugin', 'site_secret');

if (empty($configuredsitekey) || empty($configuredsitesecret)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Plugin not configured']);
    exit;
}

if ($sitekey !== $configuredsitekey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid site_key']);
    exit;
}

// Verify timestamp is within 5 minutes (prevent replay attacks).
$now = time();
if (abs($now - $timestamp) > 300) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Request expired']);
    exit;
}

// Verify HMAC signature.
// Build payload for signing (exclude signature and timestamp from the signed payload).
$payloadforsigning = $data;
unset($payloadforsigning['signature']);
unset($payloadforsigning['timestamp']);

// Sort keys recursively for consistent signature computation.
$sortedpayload = local_mc_plugin_sort_keys_recursive($payloadforsigning);
$jsonpayload = json_encode($sortedpayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$message = $timestamp . '.' . $jsonpayload;
$expectedsignature = hash_hmac('sha256', $message, $configuredsitesecret);

if (!hash_equals($expectedsignature, $signature)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid signature']);
    exit;
}

// Handle actions.
switch ($action) {
    case 'update_monitored_events':
        // Update the monitored events configuration.
        // Supports both old format (array of strings) and new format (array of objects with course_filter).
        $events = $data['events'] ?? [];

        if (!is_array($events)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Events must be an array']);
            exit;
        }

        // Detect format and normalize to new format.
        // Old format: array of event type strings.
        // New format: array of objects with event_type and course_filter.
        $normalizedevents = [];
        $validevents = [];

        foreach ($events as $event) {
            if (is_string($event)) {
                // Old format - just event type string, no course filter.
                if (preg_match('/^\\\\?[a-zA-Z_][a-zA-Z0-9_\\\\]*$/', $event)) {
                    $normalizedevents[] = [
                        'event_type' => $event,
                        'course_filter' => null,
                    ];
                    $validevents[] = $event;
                }
            } else if (is_array($event) && isset($event['event_type'])) {
                // New format - object with event_type and optional course_filter.
                $eventtype = $event['event_type'];
                if (is_string($eventtype) && preg_match('/^\\\\?[a-zA-Z_][a-zA-Z0-9_\\\\]*$/', $eventtype)) {
                    $coursefilter = $event['course_filter'] ?? null;

                    // Validate course_filter structure if present.
                    if ($coursefilter !== null) {
                        if (
                            !is_array($coursefilter) ||
                            !isset($coursefilter['mode']) ||
                            !in_array($coursefilter['mode'], ['include', 'exclude'])
                        ) {
                            // Invalid filter, treat as no filter.
                            $coursefilter = null;
                        } else if (!isset($coursefilter['course_ids']) || !is_array($coursefilter['course_ids'])) {
                            // Missing or invalid course_ids, treat as no filter.
                            $coursefilter = null;
                        } else {
                            // Ensure course_ids are integers.
                            $coursefilter['course_ids'] = array_map('intval', $coursefilter['course_ids']);
                        }
                    }

                    $normalizedevents[] = [
                        'event_type' => $eventtype,
                        'course_filter' => $coursefilter,
                    ];
                    $validevents[] = $eventtype;
                }
            }
        }

        // Store event types as comma-separated string (for backward compatibility with observer).
        $eventsstring = implode(',', $validevents);
        set_config('monitored_events', $eventsstring, 'local_mc_plugin');

        // Store full event config with course filters as JSON.
        $eventsconfigjson = json_encode($normalizedevents);
        set_config('monitored_events_config', $eventsconfigjson, 'local_mc_plugin');

        // Purge caches to ensure the observer picks up the change.
        purge_caches();

        echo json_encode([
            'success' => true,
            'message' => 'Monitored events updated',
            'event_count' => count($validevents),
            'has_course_filters' => count(array_filter($normalizedevents, function ($e) {
                return $e['course_filter'] !== null;
            })) > 0,
        ]);
        break;

    case 'get_all_events':
        // Return all available event schemas (for initial sync).
        require_once(__DIR__ . '/classes/local/event_discovery.php');
        require_once(__DIR__ . '/classes/local/dynamic_inspector.php');

        $discovery = new \local_mc_plugin\local\event_discovery();
        $allevents = $discovery->get_all_events();

        // Get schemas for all events.
        $eventclasses = array_column($allevents, 'class');
        $inspector = new \local_mc_plugin\local\dynamic_inspector();
        $schemas = $inspector->get_event_schemas($eventclasses);

        echo json_encode([
            'success' => true,
            'events' => $schemas,
            'event_count' => count($schemas),
        ]);
        break;

    case 'ping':
        // Simple health check - returns basic info to verify connectivity.
        echo json_encode([
            'success' => true,
            'message' => 'pong',
            'moodle_version' => $CFG->version,
            'plugin_version' => get_config('local_mc_plugin', 'version'),
        ]);
        break;

    case 'health':
        // Comprehensive health check for MoodleConnect to verify connectivity.
        // Returns detailed info about the Moodle instance and plugin status.
        // Optionally triggers sync of events and/or courses if requested.
        $sitename = $CFG->fullname ?? '';
        $siteurl = $CFG->wwwroot ?? '';
        $pluginversion = get_config('local_mc_plugin', 'version');
        $monitoredevents = get_config('local_mc_plugin', 'monitored_events');
        $eventcount = empty($monitoredevents) ? 0 : count(array_filter(explode(',', $monitoredevents)));

        // Check if sync is requested (optional parameters).
        $syncevents = !empty($data['sync_events']);
        $synccourses = !empty($data['sync_courses']);

        $syncresults = null;
        if ($syncevents || $synccourses) {
            require_once(__DIR__ . '/classes/local/moodleconnect_client.php');
            $syncresults = [];

            if ($syncevents) {
                $eventresult = \local_mc_plugin\local\moodleconnect_client::sync_all_events();
                $syncresults['events'] = [
                    'success' => $eventresult['success'],
                    'count' => $eventresult['event_count'] ?? 0,
                ];
            }

            if ($synccourses) {
                $courseresult = \local_mc_plugin\local\moodleconnect_client::sync_all_courses();
                $syncresults['courses'] = [
                    'success' => $courseresult['success'],
                    'count' => $courseresult['count'] ?? 0,
                ];
            }
        }

        $response = [
            'success' => true,
            'status' => 'healthy',
            'site' => [
                'name' => $sitename,
                'url' => $siteurl,
                'moodle_version' => $CFG->version,
                'moodle_release' => $CFG->release ?? null,
            ],
            'plugin' => [
                'version' => $pluginversion,
                'release' => get_config('local_mc_plugin', 'release') ?: null,
                'monitored_event_count' => $eventcount,
            ],
            'timestamp' => time(),
        ];

        if ($syncresults !== null) {
            $response['sync'] = $syncresults;
        }

        echo json_encode($response);
        break;

    case 'update_limit_status':
        // Update event limit status - blocks/unblocks event sending based on usage limits.
        // MoodleConnect pushes this when user exceeds their monthly event quota.
        $blocked = !empty($data['blocked']);
        $blockeduntil = (int) ($data['blocked_until'] ?? 0);
        $usage = $data['usage'] ?? [];
        $message = $data['message'] ?? '';

        // Store the blocked_until timestamp - observer checks this before sending events.
        set_config('events_blocked_until', $blockeduntil, 'local_mc_plugin');
        set_config('events_limit_message', $message, 'local_mc_plugin');
        set_config('events_limit_usage', json_encode($usage), 'local_mc_plugin');

        // Show admin notification if blocked.
        if ($blocked && !empty($message)) {
            // Store notification for display in admin pages.
            set_config('events_limit_notification', $message, 'local_mc_plugin');
        } else {
            // Clear notification when unblocked.
            set_config('events_limit_notification', '', 'local_mc_plugin');
        }

        echo json_encode([
            'success' => true,
            'blocked' => $blocked,
            'blocked_until' => $blockeduntil,
            'message' => $blocked ? 'Events blocked until limit resets' : 'Events unblocked',
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
        break;
}
