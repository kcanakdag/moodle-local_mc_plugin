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
 * @copyright  2025 Kerem Canakdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

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
        
        // Check if it's an associative array (object in JSON terms)
        $is_assoc = array_keys($data) !== range(0, count($data) - 1);
        
        if ($is_assoc) {
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
     * @param string $site_secret The site secret for signing
     * @return string Hex-encoded HMAC signature
     */
    private static function compute_signature($timestamp, $payload, $site_secret) {
        global $CFG;
        
        // Sort keys recursively for consistent signature computation
        $sorted_payload = self::sort_keys_recursive($payload);
        // Use compact JSON format (no spaces) to match backend
        $json_payload = json_encode($sorted_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $message = $timestamp . '.' . $json_payload;
        
        return hash_hmac('sha256', $message, $site_secret);
    }

    /**
     * Send event to MoodleConnect (fire-and-forget, non-blocking)
     * 
     * @param string $event_type
     * @param array $data
     * @return array ['success' => bool, 'message' => string]
     */
    public static function send_event($event_type, $data) {
        $base_url = local_mc_plugin_get_api_url();
        $site_key = get_config('local_mc_plugin', 'site_key');
        $site_secret = get_config('local_mc_plugin', 'site_secret');
        
        if (empty($site_key)) {
            return ['success' => false, 'message' => 'Missing Site Key'];
        }
        
        if (empty($site_secret)) {
            return ['success' => false, 'message' => 'Missing Site Secret'];
        }

        $url = $base_url . '/events';
        $timestamp = time();
        
        $payload = [
            'site_key' => $site_key,
            'event_type' => $event_type,
            'data' => $data
        ];
        
        // Compute HMAC signature
        $signature = self::compute_signature($timestamp, $payload, $site_secret);
        
        // Add signature and timestamp to payload
        $payload['signature'] = $signature;
        $payload['timestamp'] = $timestamp;

        // Use fire-and-forget for events to not block page load
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
        $base_url = local_mc_plugin_get_api_url();
        $site_key = get_config('local_mc_plugin', 'site_key');
        $site_secret = get_config('local_mc_plugin', 'site_secret');
        
        if (empty($site_key)) {
            return ['success' => false, 'message' => 'Missing Site Key'];
        }
        
        if (empty($site_secret)) {
            return ['success' => false, 'message' => 'Missing Site Secret'];
        }

        $url = $base_url . '/events/schema';
        $timestamp = time();
        
        $payload = [
            'site_key' => $site_key,
            'events' => $events
        ];
        
        // Compute HMAC signature
        $signature = self::compute_signature($timestamp, $payload, $site_secret);
        
        // Add signature and timestamp to payload
        $payload['signature'] = $signature;
        $payload['timestamp'] = $timestamp;

        return self::post_json($url, $payload);
    }

    /**
     * Helper to POST JSON data (blocking, waits for response)
     * 
     * @param string $url
     * @param array $payload
     * @param int $timeout Timeout in seconds
     * @return array ['success' => bool, 'message' => string]
     */
    private static function post_json($url, $payload, $timeout = 10) {
        // Sort keys and use same JSON flags as signature computation
        $sorted_payload = self::sort_keys_recursive($payload);
        $json = json_encode($sorted_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json)
        ]);
        
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($result === false) {
            return ['success' => false, 'message' => 'Connection failed: ' . $curl_error];
        }

        if ($httpcode >= 200 && $httpcode < 300) {
            return ['success' => true, 'message' => 'Success'];
        } else {
            return ['success' => false, 'message' => "HTTP $httpcode: $result"];
        }
    }

    /**
     * Fire-and-forget POST - sends request without waiting for response
     * Uses very short timeout to minimize blocking
     * 
     * @param string $url
     * @param array $payload
     * @return array ['success' => bool, 'message' => string]
     */
    private static function post_json_async($url, $payload) {
        // Sort keys and use same JSON flags as signature computation
        $sorted_payload = self::sort_keys_recursive($payload);
        $json = json_encode($sorted_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 300);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json)
        ]);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        
        curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpcode >= 200 && $httpcode < 300) {
            return ['success' => true, 'message' => 'Sent'];
        } else if ($httpcode == 0) {
            return ['success' => true, 'message' => 'Sent (async)'];
        } else {
            return ['success' => false, 'message' => "HTTP $httpcode"];
        }
    }
}
