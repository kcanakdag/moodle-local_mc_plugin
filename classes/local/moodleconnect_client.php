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
 * MoodleConnect API client for sending events and syncing schemas.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/../../lib.php');

/**
 * MoodleConnect API client for sending events and syncing schemas.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodleconnect_client {
    /**
     * Recursively sort array keys for consistent JSON encoding.
     *
     * @param mixed $data The data to sort
     * @return mixed The sorted data
     */
    private static function sort_keys_recursive($data) {
        if (!is_array($data)) {
            return $data;
        }

        // Check if it's an associative array (object in JSON terms).
        $isassoc = array_keys($data) !== range(0, count($data) - 1);

        if ($isassoc) {
            ksort($data);
        }

        foreach ($data as $key => $value) {
            $data[$key] = self::sort_keys_recursive($value);
        }

        return $data;
    }

    /**
     * Compute HMAC-SHA256 signature for request authentication.
     *
     * The signature is computed over: "{timestamp}.{json_payload}"
     * where json_payload is JSON with sorted keys and compact format.
     *
     * @param int $timestamp Unix timestamp
     * @param array $payload Request payload (will be JSON encoded)
     * @param string $sitesecret The site secret for signing
     * @return string Hex-encoded HMAC signature
     */
    private static function compute_signature($timestamp, $payload, $sitesecret) {
        global $CFG;

        // Sort keys recursively for consistent signature computation.
        $sortedpayload = self::sort_keys_recursive($payload);
        // Use compact JSON format (no spaces) to match backend.
        $jsonpayload = json_encode($sortedpayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $message = $timestamp . '.' . $jsonpayload;

        return hash_hmac('sha256', $message, $sitesecret);
    }

    /**
     * Send event to MoodleConnect (fire-and-forget, non-blocking).
     *
     * @param string $eventtype Event type
     * @param array $data Event data
     * @return array ['success' => bool, 'message' => string]
     */
    public static function send_event($eventtype, $data) {
        $baseurl = local_mc_plugin_get_api_url();
        $sitekey = get_config('local_mc_plugin', 'site_key');
        $sitesecret = get_config('local_mc_plugin', 'site_secret');

        if (empty($sitekey)) {
            return ['success' => false, 'message' => get_string('error_missing_site_key', 'local_mc_plugin')];
        }

        if (empty($sitesecret)) {
            return ['success' => false, 'message' => get_string('error_missing_site_secret', 'local_mc_plugin')];
        }

        $url = $baseurl . '/events';
        $timestamp = time();

        $payload = [
            'site_key' => $sitekey,
            'event_type' => $eventtype,
            'data' => $data,
        ];

        // Compute HMAC signature.
        $signature = self::compute_signature($timestamp, $payload, $sitesecret);

        // Add signature and timestamp to payload.
        $payload['signature'] = $signature;
        $payload['timestamp'] = $timestamp;

        // Use fire-and-forget for events to not block page load.
        return self::post_json_async($url, $payload);
    }

    /**
     * Sync event schemas to MoodleConnect.
     * Called when user changes monitored events in settings.
     *
     * @param array $events Array of event schemas from dynamic_inspector
     * @return array ['success' => bool, 'message' => string]
     */
    public static function sync_schema($events) {
        $baseurl = local_mc_plugin_get_api_url();
        $sitekey = get_config('local_mc_plugin', 'site_key');
        $sitesecret = get_config('local_mc_plugin', 'site_secret');

        if (empty($sitekey)) {
            return ['success' => false, 'message' => get_string('error_missing_site_key', 'local_mc_plugin')];
        }

        if (empty($sitesecret)) {
            return ['success' => false, 'message' => get_string('error_missing_site_secret', 'local_mc_plugin')];
        }

        $url = $baseurl . '/events/schema';
        $timestamp = time();

        $payload = [
            'site_key' => $sitekey,
            'events' => $events,
        ];

        // Compute HMAC signature.
        $signature = self::compute_signature($timestamp, $payload, $sitesecret);

        // Add signature and timestamp to payload.
        $payload['signature'] = $signature;
        $payload['timestamp'] = $timestamp;

        return self::post_json($url, $payload);
    }

    /**
     * Helper to POST JSON data (blocking, waits for response).
     *
     * Uses Moodle's curl wrapper which handles proxy configurations.
     *
     * @param string $url URL to post to
     * @param array $payload Payload data
     * @param int $timeout Timeout in seconds
     * @return array ['success' => bool, 'message' => string]
     */
    private static function post_json($url, $payload, $timeout = 10) {
        // Sort keys and use same JSON flags as signature computation.
        $sortedpayload = self::sort_keys_recursive($payload);
        $json = json_encode($sortedpayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Bypass Moodle's URL security for private IPs (local development only).
        // Production URLs use public IPs and standard ports, so security checks pass normally.
        $curloptions = ['proxy' => true];
        $parsedurl = parse_url($url);
        $host = $parsedurl['host'] ?? '';
        $isprivateip = filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        if ($isprivateip) {
            $curloptions['ignoresecurity'] = true;
        }

        $curl = new \curl($curloptions);
        $curl->setopt([
            'timeout' => $timeout,
            'connecttimeout' => 5,
        ]);
        $curl->setHeader([
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json),
        ]);

        $result = $curl->post($url, $json);

        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;
        $curlerror = $curl->get_errno() ? $curl->error : '';

        if ($curl->get_errno()) {
            return ['success' => false, 'message' => get_string('error_connection_failed', 'local_mc_plugin', $curlerror)];
        }

        if ($httpcode >= 200 && $httpcode < 300) {
            return ['success' => true, 'message' => get_string('success', 'local_mc_plugin')];
        } else {
            return ['success' => false, 'message' => "HTTP $httpcode: $result"];
        }
    }

    /**
     * Fire-and-forget POST - sends request with short timeout.
     *
     * Uses Moodle's curl wrapper which handles proxy configurations.
     * Uses very short timeout to minimize blocking.
     *
     * @param string $url URL to post to
     * @param array $payload Payload data
     * @return array ['success' => bool, 'message' => string]
     */
    private static function post_json_async($url, $payload) {
        // Sort keys and use same JSON flags as signature computation.
        $sortedpayload = self::sort_keys_recursive($payload);
        $json = json_encode($sortedpayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Bypass Moodle's URL security for private IPs (local development only).
        // Production URLs use public IPs and standard ports, so security checks pass normally.
        $curloptions = ['proxy' => true];
        $parsedurl = parse_url($url);
        $host = $parsedurl['host'] ?? '';
        $isprivateip = filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        if ($isprivateip) {
            $curloptions['ignoresecurity'] = true;
        }

        $curl = new \curl($curloptions);
        // Use 1 second timeout - Moodle's curl doesn't support millisecond timeouts.
        $curl->setopt([
            'timeout' => 1,
            'connecttimeout' => 1,
            'fresh_connect' => true,
        ]);
        $curl->setHeader([
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json),
        ]);

        $curl->post($url, $json);

        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if ($httpcode >= 200 && $httpcode < 300) {
            return ['success' => true, 'message' => get_string('sent', 'local_mc_plugin')];
        } else if ($httpcode == 0) {
            // Timeout is expected for async - request was likely sent.
            return ['success' => true, 'message' => get_string('sent_async', 'local_mc_plugin')];
        } else {
            return ['success' => false, 'message' => "HTTP $httpcode"];
        }
    }
}
