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
 * Certificates metadata provider.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\metadata;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Provides certificate metadata for UI dropdowns.
 *
 * Returns certificates from mod_customcert with id, name, courseid, and course_name.
 * Returns empty array if mod_customcert is not installed.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificates_provider implements metadata_provider {
    /**
     * Get the metadata type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'certificates';
    }

    /**
     * Get all certificates from mod_customcert.
     *
     * Checks that mod_customcert is installed before querying.
     *
     * @return array List of certificates with id, name, courseid, course_name.
     */
    public function get_all(): array {
        global $DB;

        $plugindir = \core_component::get_component_directory('mod_customcert');
        if ($plugindir === null || !file_exists($plugindir)) {
            return [];
        }

        $sql = "SELECT cc.id, cc.name, cc.course AS courseid, c.fullname AS course_name
                  FROM {customcert} cc
                  JOIN {course} c ON c.id = cc.course
              ORDER BY c.fullname, cc.name";
        $certs = $DB->get_records_sql($sql);

        return array_values(array_map(function ($cert) {
            return [
                'id' => (int) $cert->id,
                'name' => $cert->name,
                'courseid' => (int) $cert->courseid,
                'course_name' => $cert->course_name,
            ];
        }, $certs));
    }
}
