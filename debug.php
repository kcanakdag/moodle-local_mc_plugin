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
 * Debug page for MoodleConnect plugin configuration and event monitoring.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

use local_mc_plugin\local\moodleconnect_client;

$PAGE->set_url(new moodle_url('/local/mc_plugin/debug.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('MoodleConnect Debug');
$PAGE->set_heading('MoodleConnect Debug');

echo $OUTPUT->header();

// Check config
$base_url = get_config('local_mc_plugin', 'moodleconnect_url');
$site_key = get_config('local_mc_plugin', 'site_key');
$monitored_events = get_config('local_mc_plugin', 'monitored_events');
$debug_mode = get_config('local_mc_plugin', 'debug_mode');

echo '<h3>Configuration</h3>';
echo '<table class="generaltable">';
echo '<tr><th>Setting</th><th>Value</th></tr>';
echo '<tr><td>API URL</td><td>' . (local_mc_plugin_get_api_url() ?: '<em>Not set</em>') . '</td></tr>';
echo '<tr><td>Site Key</td><td>' . ($site_key ?: '<em>Not set</em>') . '</td></tr>';
echo '<tr><td>Debug Mode</td><td>' . ($debug_mode ? 'ON' : 'OFF') . '</td></tr>';
echo '<tr><td>Monitored Events</td><td>' . ($monitored_events ?: '<em>None</em>') . '</td></tr>';
echo '</table>';

// Check event observers
echo '<h3>Event Observers</h3>';
$observers = \core\event\manager::get_all_observers();

// Find our observers
$our_observers = [];
foreach ($observers as $eventname => $obs_list) {
    foreach ($obs_list as $obs) {
        if (isset($obs['callable']) && is_array($obs['callable']) && 
            strpos($obs['callable'][0] ?? '', 'local_mc_plugin') !== false) {
            $our_observers[$eventname] = $obs;
        }
    }
}

if (empty($our_observers)) {
    echo '<p style="color:red;"><strong>WARNING:</strong> No local_mc_plugin observers found! Did you purge caches?</p>';
} else {
    echo '<p style="color:green;"><strong>Found ' . count($our_observers) . ' observer(s):</strong></p>';
    echo '<ul>';
    foreach ($our_observers as $eventname => $obs) {
        echo '<li>' . $eventname . ' → ' . print_r($obs['callable'], true) . '</li>';
    }
    echo '</ul>';
}

// Debug log location
echo '<h3>Debug Log</h3>';
$logfile = $CFG->dataroot . '/moodleconnect_debug.log';
if (file_exists($logfile)) {
    $log_content = file_get_contents($logfile);
    $lines = explode("\n", $log_content);
    $recent = array_slice($lines, -20);
    echo '<pre style="background:#f5f5f5;padding:10px;max-height:300px;overflow:auto;">';
    echo htmlspecialchars(implode("\n", $recent));
    echo '</pre>';
} else {
    echo '<p>No debug log found at: ' . $logfile . '</p>';
    echo '<p>Enable debug mode and trigger an event to create the log.</p>';
}

echo $OUTPUT->footer();
