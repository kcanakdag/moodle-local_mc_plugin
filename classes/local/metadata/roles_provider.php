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
 * Roles metadata provider.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\metadata;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Provides role metadata for UI dropdowns.
 *
 * Returns all roles with id, shortname, name, and archetype.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roles_provider implements metadata_provider {
    /**
     * Get the metadata type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'roles';
    }

    /**
     * Get all roles.
     *
     * @return array List of roles with id, shortname, name, archetype.
     */
    public function get_all(): array {
        global $DB;

        $roles = $DB->get_records('role', [], 'sortorder ASC', 'id, shortname, name, archetype');

        return array_values(array_map(function ($r) {
            // Use role_get_name() for the localised display name.
            // The raw 'name' column is often empty in Moodle; the
            // actual display name comes from language strings.
            $displayname = role_get_name($r);

            return [
                'id' => (int) $r->id,
                'shortname' => $r->shortname,
                'name' => $displayname,
                'archetype' => $r->archetype,
            ];
        }, $roles));
    }
}
