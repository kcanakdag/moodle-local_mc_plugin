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
 * Badges metadata provider.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\metadata;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Provides badge metadata for UI dropdowns.
 *
 * Returns badges with id, name, type (site/course), and courseid.
 * Returns empty array if badges are disabled in site configuration.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badges_provider implements metadata_provider {
    /**
     * Get the metadata type identifier.
     *
     * @return string
     */
    public function get_type(): string {
        return 'badges';
    }

    /**
     * Get all active badges.
     *
     * Checks $CFG->enablebadges before querying. Badge type 1 = site badge,
     * type 2 = course badge per Moodle core constants.
     *
     * @return array List of badges with id, name, type, courseid.
     */
    public function get_all(): array {
        global $DB, $CFG;

        if (empty($CFG->enablebadges)) {
            return [];
        }

        // Active badges: status 1 (BADGE_STATUS_ACTIVE) or 3 (BADGE_STATUS_ACTIVE_LOCKED).
        $sql = "SELECT id, name, type, courseid, status
                  FROM {badge}
                 WHERE status IN (1, 3)
              ORDER BY name ASC";
        $badges = $DB->get_records_sql($sql);

        return array_values(array_map(function ($b) {
            return [
                'id' => (int) $b->id,
                'name' => $b->name,
                'type' => ((int) $b->type === 1) ? 'site' : 'course',
                'courseid' => $b->courseid ? (int) $b->courseid : null,
            ];
        }, $badges));
    }
}
