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
 * AJAX endpoint for OAuth-style connection flow.
 *
 * This endpoint handles:
 * - Generating connection tokens via POST to MoodleConnect /connect/init
 * - Returning the token to JavaScript for the polling flow
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $CFG;
require_once($CFG->libdir . '/filelib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

// Verify sesskey for security.
require_sesskey();

header('Content-Type: application/json');

$action = required_param('action', PARAM_ALPHA);

if ($action === 'init') {
    // Rate limiting: max 5 connection attempts per minute per user.
    $cachekey = 'connect_attempts_' . $USER->id;
    $cache = \cache::make('local_mc_plugin', 'mc_metadata');
    $attempts = $cache->get($cachekey);

    if ($attempts === false) {
        $attempts = ['count' => 0, 'reset_time' => time() + 60];
    }

    // Reset counter if time window expired.
    if (time() > $attempts['reset_time']) {
        $attempts = ['count' => 0, 'reset_time' => time() + 60];
    }

    if ($attempts['count'] >= 5) {
        echo json_encode([
            'success' => false,
            'message' => get_string('error_rate_limit', 'local_mc_plugin'),
        ]);
        exit;
    }

    $attempts['count']++;
    $cache->set($cachekey, $attempts);

    // Generate a connection token by calling MoodleConnect API.
    $baseurl = local_mc_plugin_get_api_url();
    $initurl = $baseurl . '/connect/init';

    // Get Moodle site info.
    $moodleurl = $CFG->wwwroot;
    $moodlesitename = $SITE->fullname;

    // Get plugin version for compatibility check.
    $pluginversion = get_config('local_mc_plugin', 'version');

    $payload = [
        'moodle_url' => $moodleurl,
        'moodle_site_name' => $moodlesitename,
        'plugin_version' => $pluginversion,
    ];

    $json = json_encode($payload);

    // Use Moodle's curl wrapper which handles proxy configurations.
    // Bypass Moodle's URL security for private IPs (local development only).
    // Production URLs use public IPs and standard ports, so security checks pass normally.
    $curloptions = ['proxy' => true];
    $parsedurl = parse_url($initurl);
    $host = $parsedurl['host'] ?? '';
    $isprivateip = filter_var($host, FILTER_VALIDATE_IP) !== false
        && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    if ($isprivateip) {
        $curloptions['ignoresecurity'] = true;
    }
    $curl = new \curl($curloptions);
    $curl->setopt([
        'timeout' => 15,
        'connecttimeout' => 10,
    ]);
    $curl->setHeader([
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json),
    ]);

    $result = $curl->post($initurl, $json);

    $info = $curl->get_info();
    $httpcode = $info['http_code'] ?? 0;
    $curlerror = $curl->get_errno() ? $curl->error : '';

    if ($curl->get_errno()) {
        echo json_encode([
            'success' => false,
            'message' => get_string('error_connection_failed', 'local_mc_plugin', $curlerror),
        ]);
        exit;
    }

    $response = json_decode($result, true);

    if ($httpcode >= 200 && $httpcode < 300 && isset($response['token'])) {
        echo json_encode([
            'success' => true,
            'token' => $response['token'],
            'expires_at' => $response['expires_at'] ?? null,
        ]);
    } else {
        $errormessage = $response['error'] ?? $response['message'] ?? "HTTP $httpcode";
        echo json_encode([
            'success' => false,
            'message' => $errormessage,
        ]);
    }
    exit;
}

// Unknown action.
echo json_encode([
    'success' => false,
    'message' => get_string('error_unknown_action', 'local_mc_plugin', $action),
]);
