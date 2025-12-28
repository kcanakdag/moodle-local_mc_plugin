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
 * Library functions for MoodleConnect plugin.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Get the MoodleConnect API URL.
 *
 * For local development, you can override the default production URL by setting
 * this in config.php:
 *
 *   $CFG->local_mc_plugin_moodleconnect_url = 'http://172.17.0.1:5000/api';
 *
 * @return string The MoodleConnect API URL
 */
function local_mc_plugin_get_api_url() {
    global $CFG;

    // Check for config.php override (for development).
    if (!empty($CFG->local_mc_plugin_moodleconnect_url)) {
        return rtrim($CFG->local_mc_plugin_moodleconnect_url, '/');
    }

    // Default production URL.
    return 'https://moodleconnect.com/api';
}

/**
 * Get the MoodleConnect frontend URL.
 *
 * For local development where frontend runs on a different port than the API,
 * you can override this in config.php:
 *
 *   $CFG->local_mc_plugin_moodleconnect_frontend_url = 'http://172.17.0.1:5173';
 *
 * In production, the frontend URL is derived from the API URL by removing /api.
 *
 * @return string The MoodleConnect frontend URL
 */
function local_mc_plugin_get_frontend_url() {
    global $CFG;

    // Check for config.php override (for development).
    if (!empty($CFG->local_mc_plugin_moodleconnect_frontend_url)) {
        return rtrim($CFG->local_mc_plugin_moodleconnect_frontend_url, '/');
    }

    // Derive from API URL by removing /api suffix.
    $apiurl = local_mc_plugin_get_api_url();
    return preg_replace('/\/api\/?$/', '', $apiurl);
}

/**
 * Recursively sort array keys for consistent JSON encoding.
 *
 * Used for HMAC signature computation to ensure consistent ordering.
 *
 * @param mixed $data The data to sort
 * @return mixed The sorted data
 */
function local_mc_plugin_sort_keys_recursive($data) {
    if (!is_array($data)) {
        return $data;
    }

    // Check if it's an associative array (object in JSON terms).
    $isassoc = array_keys($data) !== range(0, count($data) - 1);

    if ($isassoc) {
        ksort($data);
    }

    foreach ($data as $key => $value) {
        $data[$key] = local_mc_plugin_sort_keys_recursive($value);
    }

    return $data;
}

/**
 * Check if events are currently blocked due to limit exceeded.
 *
 * @return bool True if events are blocked
 */
function local_mc_plugin_is_events_blocked() {
    $blockeduntil = (int) get_config('local_mc_plugin', 'events_blocked_until');
    return $blockeduntil > 0 && time() < $blockeduntil;
}

/**
 * Get the event limit notification message if events are blocked.
 *
 * @return string|null The notification message or null if not blocked
 */
function local_mc_plugin_get_limit_notification() {
    if (!local_mc_plugin_is_events_blocked()) {
        return null;
    }

    return get_config('local_mc_plugin', 'events_limit_notification');
}
