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
 * External functions and services for local_mc_plugin.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_mc_plugin_get_analytics' => [
        'classname' => 'local_mc_plugin\external\get_analytics',
        'description' => 'Get analytics data for the dashboard',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_mc_plugin_connect_init' => [
        'classname' => 'local_mc_plugin\external\connect_init',
        'description' => 'Initialize the MoodleConnect connection flow',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_mc_plugin_save_settings' => [
        'classname' => 'local_mc_plugin\external\save_settings',
        'description' => 'Save plugin settings (credentials, events, debug mode)',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_mc_plugin_get_connection_status' => [
        'classname' => 'local_mc_plugin\external\get_connection_status',
        'description' => 'Check connection status with MoodleConnect',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_mc_plugin_sync_events' => [
        'classname' => 'local_mc_plugin\external\sync_events',
        'description' => 'Sync monitored event schemas to MoodleConnect',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_mc_plugin_sync_all_events' => [
        'classname' => 'local_mc_plugin\external\sync_all_events',
        'description' => 'Sync all available event schemas to MoodleConnect',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_mc_plugin_bulk_sync_count' => [
        'classname' => 'local_mc_plugin\external\bulk_sync_count',
        'description' => 'Count active users for bulk sync preflight',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_mc_plugin_bulk_sync_fire' => [
        'classname' => 'local_mc_plugin\external\bulk_sync_fire',
        'description' => 'Fire user_updated events in batches for bulk sync',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
