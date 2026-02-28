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
 * Groups metadata provider.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\metadata;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Provides group metadata for UI dropdowns.
 *
 * Returns all groups with id, name, courseid, and course_name.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class groups_provider implements metadata_provider {
    /**
     * Get the metadata type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'groups';
    }

    /**
     * Get all groups with their course context.
     *
     * @return array List of groups with id, name, courseid, course_name.
     */
    public function get_all(): array {
        global $DB;

        $sql = "SELECT g.id, g.name, g.courseid, c.fullname AS course_name
                  FROM {groups} g
                  JOIN {course} c ON c.id = g.courseid
              ORDER BY c.fullname, g.name";
        $groups = $DB->get_records_sql($sql);

        return array_values(array_map(function ($g) {
            return [
                'id' => (int) $g->id,
                'name' => $g->name,
                'courseid' => (int) $g->courseid,
                'course_name' => $g->course_name,
            ];
        }, $groups));
    }
}
