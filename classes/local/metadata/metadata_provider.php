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
 * Interface for metadata providers.
 *
 * Metadata providers fetch site data (courses, roles, groups, etc.)
 * for populating UI dropdowns in MoodleConnect action configuration.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\metadata;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Interface for metadata providers.
 *
 * Each provider fetches a specific type of metadata from the Moodle instance
 * and returns it in a standardized format for caching and UI display.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface metadata_provider {
    /**
     * Get the metadata type identifier.
     *
     * @return string The type name (e.g., 'courses', 'roles', 'groups').
     */
    public function get_type(): string;

    /**
     * Get all metadata items of this type.
     *
     * Returns an array of associative arrays, each representing one item.
     * The structure varies by type but always includes 'id' and 'name'.
     *
     * @return array List of metadata items.
     */
    public function get_all(): array;
}
