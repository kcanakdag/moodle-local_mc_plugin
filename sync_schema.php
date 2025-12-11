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
 * Schema sync endpoint for MoodleConnect.
 *
 * Handles AJAX requests for syncing event schemas and checking connection status.
 * Also provides a standalone sync UI for advanced users.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$action = optional_param('action', '', PARAM_ALPHA);

// ========================================
// AJAX: Check connection status
// ========================================
if ($action === 'status') {
    header('Content-Type: application/json');

    $base_url = local_mc_plugin_get_api_url();
    $test_url = preg_replace('/\/api$/', '', $base_url);

    $site_key = get_config('local_mc_plugin', 'site_key');

    if (empty($site_key)) {
        echo json_encode(['configured' => false, 'connected' => false]);
        exit;
    }

    // Check connection by calling the site status endpoint (with activate=true to auto-activate)
    $status_url = $base_url . '/sites/status?site_key=' . urlencode($site_key) . '&activate=true';
    $ch = curl_init($status_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $result = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($httpcode === 200 && $result) {
        $data = json_decode($result, true);
        echo json_encode([
            'configured' => true,
            'connected' => true,
            'site_name' => $data['site_name'] ?? null,
            'synced_event_count' => $data['synced_event_count'] ?? 0,
            'synced_events' => $data['synced_events'] ?? [],
        ]);
    } else {
        // Include debug info
        echo json_encode([
            'configured' => true,
            'connected' => false,
            'error' => 'Cannot reach MoodleConnect',
            'debug' => [
                'url' => $status_url,
                'http_code' => $httpcode,
                'curl_error' => $curl_error,
            ],
        ]);
    }
    exit;
}

// ========================================
// AJAX: Sync event schemas
// ========================================
if ($action === 'sync') {
    header('Content-Type: application/json');

    $site_key = get_config('local_mc_plugin', 'site_key');
    $base_url = local_mc_plugin_get_api_url();

    if (empty($site_key)) {
        echo json_encode(['success' => false, 'message' => get_string('error_no_site_key', 'local_mc_plugin')]);
        exit;
    }

    $monitored_events = get_config('local_mc_plugin', 'monitored_events');
    $eventclasses = array_filter(array_map('trim', explode(',', $monitored_events)));

    // Allow empty events - user may want to clear all monitored events
    $schemas = [];
    if (!empty($eventclasses)) {
        $inspector = new \local_mc_plugin\local\dynamic_inspector();
        $schemas = $inspector->get_event_schemas($eventclasses);
    }

    $result = \local_mc_plugin\local\moodleconnect_client::sync_schema($schemas);

    echo json_encode([
        'success' => $result['success'],
        'message' => $result['message'],
        'event_count' => count($schemas),
    ]);
    exit;
}

// ========================================
// Standalone Page UI (for direct access)
// ========================================
$PAGE->set_url(new moodle_url('/local/mc_plugin/sync_schema.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_mc_plugin') . ' - Sync');
$PAGE->set_heading(get_string('pluginname', 'local_mc_plugin'));
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('sync_schema_heading', 'local_mc_plugin'));

$site_key = get_config('local_mc_plugin', 'site_key');
$monitored_events = get_config('local_mc_plugin', 'monitored_events');

if (empty($site_key)) {
    echo $OUTPUT->notification(get_string('sync_configure_first', 'local_mc_plugin'), 'warning');
    echo html_writer::link(
        new moodle_url('/admin/settings.php', ['section' => 'local_mc_plugin']),
        get_string('sync_go_to_settings', 'local_mc_plugin'),
        ['class' => 'btn btn-primary']
    );
} else {
    $eventclasses = array_filter(array_map('trim', explode(',', $monitored_events)));

    echo html_writer::tag('p', get_string('sync_site_key_label', 'local_mc_plugin', html_writer::tag('code', $site_key)));
    echo html_writer::tag('p', get_string('sync_monitored_events_label', 'local_mc_plugin', count($eventclasses)));

    if (!empty($eventclasses)) {
        echo html_writer::start_tag('ul');
        foreach ($eventclasses as $event) {
            echo html_writer::tag('li', html_writer::tag('code', $event));
        }
        echo html_writer::end_tag('ul');
    }

    echo html_writer::start_div('mt-3');
    echo html_writer::link(
        new moodle_url('/admin/settings.php', ['section' => 'local_mc_plugin']),
        get_string('sync_back_to_settings', 'local_mc_plugin'),
        ['class' => 'btn btn-secondary mr-2']
    );
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
