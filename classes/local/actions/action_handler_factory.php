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
 * Factory for local action handlers.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\actions;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Factory that returns the correct action handler for a given action type.
 *
 * Registers all 7 action handler classes and provides methods to retrieve
 * a specific handler or check availability of all actions.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_handler_factory {
    /**
     * Map of action type to handler class.
     *
     * @var array
     */
    private static $handlers = [
        'issue_certificate' => issue_certificate_handler::class,
        'award_badge' => award_badge_handler::class,
        'send_message' => send_message_handler::class,
        'enroll_user' => enroll_user_handler::class,
        'suspend_enrolment' => suspend_enrolment_handler::class,
        'add_to_group' => add_to_group_handler::class,
        'add_to_cohort' => add_to_cohort_handler::class,
    ];

    /**
     * Get an action handler for the given type.
     *
     * @param string $actiontype The action type identifier.
     * @return action_handler The handler instance.
     * @throws \invalid_parameter_exception If the action type is unknown.
     */
    public static function get_handler(string $actiontype): action_handler {
        if (!isset(self::$handlers[$actiontype])) {
            throw new \invalid_parameter_exception("Unknown action type: {$actiontype}");
        }

        $class = self::$handlers[$actiontype];
        return new $class();
    }

    /**
     * Get availability status of all registered action types.
     *
     * Returns an associative array keyed by action type, each containing
     * 'available' (bool) and 'error' (string|null).
     *
     * @return array Availability status for each action type.
     */
    public static function get_available_actions(): array {
        $available = [];
        foreach (self::$handlers as $type => $class) {
            $handler = new $class();
            $available[$type] = $handler->check_availability();
        }
        return $available;
    }
}
