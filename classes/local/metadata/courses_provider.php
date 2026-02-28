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
 * Courses metadata provider.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\metadata;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Provides course metadata for UI dropdowns.
 *
 * Returns visible courses with id, shortname, fullname, and category name.
 * Excludes the site-level course (SITEID).
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courses_provider implements metadata_provider {
    /**
     * Get the metadata type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'courses';
    }

    /**
     * Get all visible courses.
     *
     * @return array List of courses with id, shortname, fullname, category.
     */
    public function get_all(): array {
        global $DB;

        $sql = "SELECT c.id, c.shortname, c.fullname, c.category,
                       cc.name AS category_name
                  FROM {course} c
             LEFT JOIN {course_categories} cc ON cc.id = c.category
                 WHERE c.visible = 1 AND c.id != :siteid
              ORDER BY c.fullname ASC";
        $courses = $DB->get_records_sql($sql, ['siteid' => SITEID]);

        return array_values(array_map(function ($c) {
            return [
                'id' => (int) $c->id,
                'shortname' => $c->shortname,
                'fullname' => $c->fullname,
                'category' => $c->category_name ?? '',
            ];
        }, $courses));
    }
}
