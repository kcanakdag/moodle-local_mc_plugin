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
 * Interface for local action handlers.
 *
 * Action handlers execute specific Moodle-native operations (issue certificates,
 * award badges, send messages, etc.) triggered by MoodleConnect automations.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\actions;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Interface for local action handlers.
 *
 * Each handler implements a specific action type (e.g., issue_certificate, award_badge).
 * Handlers are responsible for checking prerequisites, executing the Moodle API call,
 * and maintaining idempotency via execution records.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface action_handler {
    /**
     * Get the action type identifier.
     *
     * @return string The action type (e.g., 'issue_certificate', 'award_badge').
     */
    public function get_type(): string;

    /**
     * Check if required dependencies are available.
     *
     * Verifies that the Moodle instance has the necessary plugins or features
     * enabled for this action type.
     *
     * @return array ['available' => bool, 'error' => string|null]
     */
    public function check_availability(): array;

    /**
     * Execute the action.
     *
     * Performs the Moodle API call for this action type. Should check
     * for Moodle-level duplicates (e.g., certificate already issued)
     * in addition to the DB-level idempotency claim managed by action_executor.
     *
     * @param object $data Action data containing action_config and event_payload.
     * @return array ['success' => bool, 'result' => array, 'error' => string|null,
     *               'error_code' => string|null, 'retry' => bool|null]
     */
    public function execute(object $data): array;
}
