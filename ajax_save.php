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
 * AJAX endpoint to save plugin settings.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();
require_sesskey();
require_capability('moodle/site:config', context_system::instance());

header('Content-Type: application/json');

$action = optional_param('action', '', PARAM_ALPHA);
// Use PARAM_RAW for site_key/site_secret as they contain base64url characters (-, _).
// Accept both camelCase (from JS) and snake_case (legacy) parameter names.
$sitekey = optional_param('siteKey', null, PARAM_RAW);
$sitesecret = optional_param('siteSecret', null, PARAM_RAW);
// Fallback to snake_case for backwards compatibility.
if ($sitekey === null) {
    $sitekey = optional_param('site_key', null, PARAM_RAW);
}
if ($sitesecret === null) {
    $sitesecret = optional_param('site_secret', null, PARAM_RAW);
}

// Validate site_key and site_secret format (base64url: alphanumeric, -, _) and length.
if ($sitekey !== null) {
    $keylen = strlen($sitekey);
    if ($keylen < 16 || $keylen > 128) {
        echo json_encode(['success' => false, 'message' => 'Invalid site key length (must be 16-128 characters)']);
        exit;
    }
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $sitekey)) {
        echo json_encode(['success' => false, 'message' => get_string('error_invalid_site_key_format', 'local_mc_plugin')]);
        exit;
    }
}
if ($sitesecret !== null) {
    $secretlen = strlen($sitesecret);
    if ($secretlen < 16 || $secretlen > 128) {
        echo json_encode(['success' => false, 'message' => 'Invalid site secret length (must be 16-128 characters)']);
        exit;
    }
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $sitesecret)) {
        echo json_encode(['success' => false, 'message' => get_string('error_invalid_site_secret_format', 'local_mc_plugin')]);
        exit;
    }
}
$monitoredevents = optional_param('monitored_events', null, PARAM_RAW);
$debugmode = optional_param('debug_mode', null, PARAM_INT);

if ($action === 'save') {
    $saved = [];

    if ($sitekey !== null) {
        set_config('site_key', $sitekey, 'local_mc_plugin');
        $saved[] = 'site_key';
    }

    if ($sitesecret !== null) {
        set_config('site_secret', $sitesecret, 'local_mc_plugin');
        $saved[] = 'site_secret';
    }

    if ($monitoredevents !== null) {
        // Validate that all event classes are valid.
        if (!empty(trim($monitoredevents))) {
            $classes = array_filter(array_map('trim', explode(',', $monitoredevents)));
            foreach ($classes as $class) {
                // Normalize class name (remove leading backslash).
                $class = ltrim($class, '\\');
                $fullclass = '\\' . $class;
                
                // Check if class exists and is a valid event.
                if (!class_exists($fullclass)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid event class: ' . $class]);
                    exit;
                }
                if (!is_subclass_of($fullclass, '\\core\\event\\base')) {
                    echo json_encode(['success' => false, 'message' => 'Class is not a valid event: ' . $class]);
                    exit;
                }
            }
        }
        set_config('monitored_events', $monitoredevents, 'local_mc_plugin');
        $saved[] = 'monitored_events';
    }

    if ($debugmode !== null) {
        set_config('debug_mode', $debugmode, 'local_mc_plugin');
        $saved[] = 'debug_mode';
    }

    echo json_encode([
        'success' => true,
        'message' => get_string('success_settings_saved', 'local_mc_plugin'),
        'saved' => $saved,
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => get_string('error_unknown_action', 'local_mc_plugin', $action),
    ]);
}
