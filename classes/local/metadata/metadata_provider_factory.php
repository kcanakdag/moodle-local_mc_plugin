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
 * Factory for metadata providers.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\metadata;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Factory that returns the correct metadata provider for a given type.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class metadata_provider_factory {
    /**
     * Map of metadata type to provider class.
     *
     * @var array
     */
    private static $providers = [
        'courses' => courses_provider::class,
        'roles' => roles_provider::class,
        'groups' => groups_provider::class,
        'cohorts' => cohorts_provider::class,
        'badges' => badges_provider::class,
        'certificates' => certificates_provider::class,
    ];

    /**
     * Get a metadata provider for the given type.
     *
     * @param string $type The metadata type (courses, roles, groups, cohorts, badges, certificates).
     * @return metadata_provider The provider instance.
     * @throws \invalid_parameter_exception If the type is unknown.
     */
    public static function get_provider(string $type): metadata_provider {
        if (!isset(self::$providers[$type])) {
            throw new \invalid_parameter_exception("Unknown metadata type: {$type}");
        }

        $class = self::$providers[$type];
        return new $class();
    }

    /**
     * Get all supported metadata types.
     *
     * @return array List of supported type strings.
     */
    public static function get_supported_types(): array {
        return array_keys(self::$providers);
    }
}
