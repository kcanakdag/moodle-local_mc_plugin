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
    'local_mc_plugin_fetch_schema' => [
        'classname' => 'local_mc_plugin\external\fetch_schema',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Fetch Airtable base schema',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_mc_plugin_fetch_table_schema' => [
        'classname' => 'local_mc_plugin\external\fetch_table_schema',
        'methodname' => 'execute',
        'classpath' => '',
        'description' => 'Fetch Airtable table schema',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
