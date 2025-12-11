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
 * Cache definitions for local_mc_plugin plugin.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'concurrency' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 100,
    ],
    'entity_registry' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 3600, // 1 hour.
        'staticacceleration' => true,
        'staticaccelerationsize' => 1,
    ],
    'mc_metadata' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 3600, // 1 hour - cache for bases, tables, schemas, and event list.
        'staticacceleration' => true,
        'staticaccelerationsize' => 50,
    ],
    'rate_limits' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 2, // 2 seconds - short TTL for rate limiting
        'staticacceleration' => true,
        'staticaccelerationsize' => 100,
    ],
    'active_syncs' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 86400, // 24 hours - invalidated on edit
        'staticacceleration' => true,
        'staticaccelerationsize' => 1,
    ],
];
