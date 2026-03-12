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
 * Error codes and classification for local action handlers.
 *
 * Centralises all error codes used by action handlers and the executor,
 * categorising each as retryable (transient) or permanent (configuration).
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\actions;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Registry of error codes for local action execution.
 *
 * Each error code maps to a language string key and a retry classification.
 * Permanent errors indicate configuration issues that won't resolve on retry.
 * Retryable errors indicate transient failures that may succeed on retry.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class error_codes {
    // Request-level errors (executor).

    /** @var string Missing required field in the request. */
    const INVALID_REQUEST = 'invalid_request';

    /** @var string Action type not recognised by the factory. */
    const UNKNOWN_ACTION_TYPE = 'unknown_action_type';

    /** @var string Uncaught exception during handler execution. */
    const EXECUTION_EXCEPTION = 'execution_exception';

    /** @var string Generic fallback when handler returns no error_code. */
    const ACTION_FAILED = 'action_failed';

    // Dependency and availability errors.

    /** @var string Required plugin is not installed (e.g. mod_customcert). */
    const PLUGIN_NOT_INSTALLED = 'plugin_not_installed';

    /** @var string Badges subsystem is disabled in site configuration. */
    const BADGES_DISABLED = 'badges_disabled';

    /** @var string Manual enrolment plugin not enabled for the target course. */
    const MANUAL_ENROL_NOT_ENABLED = 'manual_enrol_not_enabled';

    /** @var string Manual enrolment plugin is not installed or enabled globally. */
    const MANUAL_ENROL_PLUGIN_MISSING = 'manual_enrol_plugin_missing';

    /** @var string An enrolment plugin required for the operation is not available. */
    const ENROLMENT_PLUGIN_MISSING = 'enrolment_plugin_missing';

    /** @var string Role ID does not exist. */
    const ROLE_NOT_FOUND = 'role_not_found';

    // Concurrency errors (executor).

    /** @var string Another request is already executing this action+fingerprint. */
    const EXECUTION_IN_PROGRESS = 'execution_in_progress';

    // Payload and configuration errors.

    /** @var string Event payload missing required user or object data. */
    const INVALID_PAYLOAD = 'invalid_payload';

    /** @var string Certificate ID does not exist. */
    const CERTIFICATE_NOT_FOUND = 'certificate_not_found';

    /** @var string Badge ID does not exist. */
    const BADGE_NOT_FOUND = 'badge_not_found';

    /** @var string Badge exists but is not active. */
    const BADGE_NOT_ACTIVE = 'badge_not_active';

    /** @var string Course ID does not exist. */
    const COURSE_NOT_FOUND = 'course_not_found';

    /** @var string Group ID does not exist. */
    const GROUP_NOT_FOUND = 'group_not_found';

    /** @var string Cohort ID does not exist. */
    const COHORT_NOT_FOUND = 'cohort_not_found';

    /** @var string Target user not found in Moodle. */
    const USER_NOT_FOUND = 'user_not_found';

    /** @var string User is not enrolled in the required course. */
    const USER_NOT_ENROLLED = 'user_not_enrolled';

    /** @var string No enrolment record found for the user/course. */
    const ENROLMENT_NOT_FOUND = 'enrolment_not_found';

    // Transient and runtime errors.

    /** @var string message_send() returned false or zero. */
    const MESSAGE_SEND_FAILED = 'message_send_failed';

    /**
     * Errors that are retryable (transient failures).
     *
     * The MoodleConnect backend will re-dispatch these up to 3 times.
     *
     * @var string[]
     */
    const RETRYABLE = [
        self::EXECUTION_EXCEPTION,
        self::EXECUTION_IN_PROGRESS,
        self::MESSAGE_SEND_FAILED,
        self::ACTION_FAILED,
    ];

    /**
     * Errors that are permanent (configuration / data issues).
     *
     * These will not be retried — the user must fix the configuration.
     *
     * @var string[]
     */
    const PERMANENT = [
        self::INVALID_REQUEST,
        self::UNKNOWN_ACTION_TYPE,
        self::PLUGIN_NOT_INSTALLED,
        self::BADGES_DISABLED,
        self::MANUAL_ENROL_NOT_ENABLED,
        self::MANUAL_ENROL_PLUGIN_MISSING,
        self::ENROLMENT_PLUGIN_MISSING,
        self::ROLE_NOT_FOUND,
        self::INVALID_PAYLOAD,
        self::CERTIFICATE_NOT_FOUND,
        self::BADGE_NOT_FOUND,
        self::BADGE_NOT_ACTIVE,
        self::COURSE_NOT_FOUND,
        self::GROUP_NOT_FOUND,
        self::COHORT_NOT_FOUND,
        self::USER_NOT_FOUND,
        self::USER_NOT_ENROLLED,
        self::ENROLMENT_NOT_FOUND,
    ];

    /**
     * Check whether an error code represents a retryable failure.
     *
     * @param string $code The error code to check.
     * @return bool True if the error is retryable.
     */
    public static function is_retryable(string $code): bool {
        return in_array($code, self::RETRYABLE, true);
    }

    /**
     * Get the language string key for an error code.
     *
     * All keys follow the pattern "action_error_{code}" in the
     * local_mc_plugin language file.
     *
     * @param string $code The error code.
     * @return string The language string key.
     */
    public static function get_string_key(string $code): string {
        return 'action_error_' . $code;
    }

    /**
     * Get a human-readable error message for an error code.
     *
     * Falls back to the raw code if the language string is not defined.
     *
     * @param string $code The error code.
     * @param mixed $a Optional parameter for the language string.
     * @return string The localised error message.
     */
    public static function get_message(string $code, $a = null): string {
        $key = self::get_string_key($code);
        if (get_string_manager()->string_exists($key, 'local_mc_plugin')) {
            return get_string($key, 'local_mc_plugin', $a);
        }
        return $code;
    }
}
