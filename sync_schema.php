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
 * Schema sync page for MoodleConnect.
 *
 * Provides a standalone sync UI showing connection status and monitored events.
 * AJAX actions are handled by External Services (classes/external/).
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

// Standalone Page UI (for direct access).
$PAGE->set_url(new moodle_url('/local/mc_plugin/sync_schema.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_mc_plugin') . ' - Sync');
$PAGE->set_heading(get_string('pluginname', 'local_mc_plugin'));
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('sync_schema_heading', 'local_mc_plugin'));

$sitekey = get_config('local_mc_plugin', 'site_key');
$monitoredevents = get_config('local_mc_plugin', 'monitored_events');

if (empty($sitekey)) {
    echo $OUTPUT->notification(get_string('sync_configure_first', 'local_mc_plugin'), 'warning');
    echo html_writer::link(
        new moodle_url('/admin/settings.php', ['section' => 'local_mc_plugin']),
        get_string('sync_go_to_settings', 'local_mc_plugin'),
        ['class' => 'btn btn-primary']
    );
} else {
    $eventclasses = array_filter(array_map('trim', explode(',', $monitoredevents)));

    echo html_writer::tag('p', get_string('sync_site_key_label', 'local_mc_plugin', html_writer::tag('code', $sitekey)));
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
