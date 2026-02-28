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
 * Cohorts metadata provider.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\metadata;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Provides cohort metadata for UI dropdowns.
 *
 * Returns visible cohorts with id, name, idnumber, and context info.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohorts_provider implements metadata_provider {
    /**
     * Get the metadata type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'cohorts';
    }

    /**
     * Get all visible cohorts with context information.
     *
     * @return array List of cohorts with id, name, idnumber, context.
     */
    public function get_all(): array {
        global $DB;

        $cohorts = $DB->get_records('cohort', ['visible' => 1], 'name ASC');

        return array_values(array_map(function ($c) {
            $contextname = 'System';
            $context = \context::instance_by_id($c->contextid, IGNORE_MISSING);
            if ($context) {
                $contextname = $context->get_context_name(false);
            }

            return [
                'id' => (int) $c->id,
                'name' => $c->name,
                'idnumber' => $c->idnumber ?? '',
                'context' => $contextname,
            ];
        }, $cohorts));
    }
}
