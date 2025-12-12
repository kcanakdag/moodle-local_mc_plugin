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
$PAGE->set_title(get_string('debug_heading', 'local_mc_plugin'));
$PAGE->set_heading(get_string('debug_heading', 'local_mc_plugin'));

echo $OUTPUT->header();

// Check config.
$baseurl = get_config('local_mc_plugin', 'moodleconnect_url');
$sitekey = get_config('local_mc_plugin', 'site_key');
$monitoredevents = get_config('local_mc_plugin', 'monitored_events');
$debugmode = get_config('local_mc_plugin', 'debug_mode');

echo '<h3>' . get_string('debug_configuration', 'local_mc_plugin') . '</h3>';
echo '<table class="generaltable">';
echo '<tr><th>' . get_string('debug_setting', 'local_mc_plugin') . '</th><th>' .
    get_string('debug_value', 'local_mc_plugin') . '</th></tr>';
echo '<tr><td>' . get_string('debug_api_url', 'local_mc_plugin') . '</td><td>' .
    (local_mc_plugin_get_api_url() ?: '<em>' . get_string('debug_not_set', 'local_mc_plugin') . '</em>') .
    '</td></tr>';
echo '<tr><td>' . get_string('debug_site_key', 'local_mc_plugin') . '</td><td>' .
    (s($sitekey) ?: '<em>' . get_string('debug_not_set', 'local_mc_plugin') . '</em>') . '</td></tr>';
echo '<tr><td>' . get_string('debug_debug_mode', 'local_mc_plugin') . '</td><td>' .
    ($debugmode ? get_string('debug_on', 'local_mc_plugin') : get_string('debug_off', 'local_mc_plugin')) .
    '</td></tr>';
echo '<tr><td>' . get_string('debug_monitored_events', 'local_mc_plugin') . '</td><td>' .
    (s($monitoredevents) ?: '<em>' . get_string('debug_none', 'local_mc_plugin') . '</em>') . '</td></tr>';
echo '</table>';

// Check event observers.
echo '<h3>' . get_string('debug_event_observers', 'local_mc_plugin') . '</h3>';
$observers = \core\event\manager::get_all_observers();

// Find our observers.
$ourobservers = [];
foreach ($observers as $eventname => $obslist) {
    foreach ($obslist as $obs) {
        if (
            isset($obs['callable']) && is_array($obs['callable']) &&
            strpos($obs['callable'][0] ?? '', 'local_mc_plugin') !== false
        ) {
            $ourobservers[$eventname] = $obs;
        }
    }
}

if (empty($ourobservers)) {
    echo '<p style="color:red;"><strong>' .
        get_string('debug_warning_no_observers', 'local_mc_plugin') . '</strong></p>';
} else {
    echo '<p style="color:green;"><strong>' .
        get_string('debug_found_observers', 'local_mc_plugin', count($ourobservers)) . '</strong></p>';
    echo '<ul>';
    foreach ($ourobservers as $eventname => $obs) {
        $callablestr = var_export($obs['callable'], true);
        echo '<li>' . $eventname . ' → ' . htmlspecialchars($callablestr) . '</li>';
    }
    echo '</ul>';
}

// Debug log location.
echo '<h3>' . get_string('debug_log', 'local_mc_plugin') . '</h3>';
$logfile = $CFG->dataroot . '/moodleconnect_debug.log';
if (file_exists($logfile)) {
    $logcontent = file_get_contents($logfile);
    $lines = explode("\n", $logcontent);
    $recent = array_slice($lines, -20);
    echo '<pre style="background:#f5f5f5;padding:10px;max-height:300px;overflow:auto;">';
    echo htmlspecialchars(implode("\n", $recent));
    echo '</pre>';
} else {
    echo '<p>' . get_string('debug_no_log_found', 'local_mc_plugin', $logfile) . '</p>';
    echo '<p>' . get_string('debug_enable_mode', 'local_mc_plugin') . '</p>';
}

echo $OUTPUT->footer();
