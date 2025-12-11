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
 * Database upgrade script for MoodleConnect plugin.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for the MoodleConnect plugin.
 *
 * Handles database schema changes when upgrading from older versions.
 *
 * @param int $oldversion The version we are upgrading from
 * @return bool Always returns true
 */
function xmldb_local_mc_plugin_upgrade($oldversion)
{
    global $DB;
    $dbman = $DB->get_manager();

    // If upgrading to the stateless MVP version, drop all old tables.
    if ($oldversion < 2025112801) {
        $tables = [
            'local_mc_plugin_map',
            'local_mc_plugin_job',
            'local_mc_plugin_sync',
            'local_mc_plugin_schema_cache',
            'local_mc_plugin_entity_map',
            'local_mc_plugin_template'
        ];

        foreach ($tables as $tablename) {
            $table = new xmldb_table($tablename);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
        }

        upgrade_plugin_savepoint(true, 2025112801, 'local', 'mc_plugin');
    }

    return true;
}
