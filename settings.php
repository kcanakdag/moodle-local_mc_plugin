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
 * Plugin settings and configuration page.
 *
 * Events are now managed automatically via reverse sync from MoodleConnect.
 * When triggers are created/updated in MoodleConnect, the monitored events
 * are automatically synced to this plugin.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_mc_plugin', get_string('pluginname', 'local_mc_plugin'));

    // Event Limit Warning (shown at top if events are blocked).
    $settings->add(new \local_mc_plugin\admin\setting_limit_warning(
        'local_mc_plugin/limit_warning'
    ));

    // Section: Connection.
    $settings->add(new admin_setting_heading(
        'local_mc_plugin/connection_heading',
        get_string('connection_heading', 'local_mc_plugin'),
        get_string('connection_heading_desc', 'local_mc_plugin')
    ));

    // Check connection status.
    $sitekey = get_config('local_mc_plugin', 'site_key');
    $sitesecret = get_config('local_mc_plugin', 'site_secret');
    $isconnected = !empty($sitekey) && !empty($sitesecret);

    // Connection Status Display with Connect Button.
    $settings->add(new \local_mc_plugin\admin\setting_connection_status(
        'local_mc_plugin/connection_status',
        '', // No event input ID needed anymore.
        $isconnected
    ));

    // Section: Synced Events (read-only display).
    $settings->add(new admin_setting_heading(
        'local_mc_plugin/synced_events_heading',
        get_string('synced_events_heading', 'local_mc_plugin'),
        get_string('synced_events_heading_desc', 'local_mc_plugin')
    ));

    // Synced Events Display (read-only).
    $settings->add(new \local_mc_plugin\admin\setting_synced_events(
        'local_mc_plugin/synced_events_display'
    ));

    // Section: Advanced.
    $settings->add(new admin_setting_heading(
        'local_mc_plugin/advanced_heading',
        get_string('advanced_heading', 'local_mc_plugin'),
        ''
    ));

    // Debug Mode.
    $settings->add(new admin_setting_configcheckbox(
        'local_mc_plugin/debug_mode',
        get_string('debug_mode', 'local_mc_plugin'),
        get_string('debug_mode_desc', 'local_mc_plugin'),
        0
    ));

    $ADMIN->add('localplugins', $settings);
}
