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
 * Inline executor for local actions.
 *
 * Executes action handlers synchronously when called from api.php.
 * Returns the result directly so MoodleConnect can update EventLog
 * without needing a separate callback.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\actions;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Executes local actions inline and returns results.
 *
 * This replaces the adhoc task approach — actions execute synchronously
 * in the api.php request, matching the plugin's existing stateless pattern.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_executor {
    /** @var string Sentinel value stored in result while execution is in progress. */
    public const CLAIM_PENDING_SENTINEL = '__pending__';

    /**
     * Execute a local action and return the result.
     *
     * Flow:
     * 1. Resolve the handler via factory
     * 2. Atomically claim idempotency key
     * 3. Execute the action
     * 4. Finalize execution record on success (or release claim on failure)
     * 5. Return structured result
     *
     * @param array $requestdata The request data from api.php containing:
     *   - action_type (string): Handler type identifier
     *   - action_config (array): Action-specific configuration
     *   - event_payload (array): Event data from MoodleConnect
     *   - event_fingerprint (string): SHA-256 hash for idempotency
     *   - event_log_id (int): MoodleConnect EventLog ID
     * @return array Structured result with success, status, result/error, execution_time_ms.
     */
    public static function execute(array $requestdata): array {
        $starttime = microtime(true);

        $actiontype = $requestdata['action_type'] ?? '';
        $actionconfig = $requestdata['action_config'] ?? [];
        $eventpayload = $requestdata['event_payload'] ?? [];
        $eventfingerprint = $requestdata['event_fingerprint'] ?? '';
        $eventlogid = $requestdata['event_log_id'] ?? 0;

        // Validate required fields.
        if (empty($actiontype)) {
            return self::build_response(false, null, 'Missing action_type', error_codes::INVALID_REQUEST, false, $starttime);
        }
        if (empty($eventfingerprint)) {
            return self::build_response(false, null, 'Missing event_fingerprint', error_codes::INVALID_REQUEST, false, $starttime);
        }

        // Get handler.
        try {
            $handler = action_handler_factory::get_handler($actiontype);
        } catch (\Exception $e) {
            return self::build_response(false, null, $e->getMessage(), error_codes::UNKNOWN_ACTION_TYPE, false, $starttime);
        }

        // Build data object for handler (matches interface expectations).
        $data = (object) [
            'action_type' => $actiontype,
            'action_config' => (object) $actionconfig,
            'event_payload' => json_decode(json_encode($eventpayload)),
            'event_fingerprint' => $eventfingerprint,
            'event_log_id' => $eventlogid,
        ];

        // Atomically claim this action+fingerprint before side effects.
        // Unique index (action_type, event_fingerprint) guarantees only one winner.
        $claim = self::claim_execution($data);
        if ($claim['status'] === 'already_processed') {
            return self::build_response(true, ['status' => 'already_processed'], null, null, false, $starttime);
        }
        if ($claim['status'] === 'in_progress') {
            return self::build_response(
                false,
                null,
                'Execution already in progress',
                error_codes::EXECUTION_IN_PROGRESS,
                true,
                $starttime
            );
        }

        // Execute the action.
        try {
            $result = $handler->execute($data);
        } catch (\Exception $e) {
            self::release_claim($claim['recordid']);
            return self::build_response(false, null, $e->getMessage(), error_codes::EXECUTION_EXCEPTION, true, $starttime);
        }

        if ($result['success']) {
            // Finalize claimed execution record for idempotency/audit trail.
            try {
                self::finalize_execution($claim['recordid'], $data, $result, $claim['user_id'] ?? null);
            } catch (\Exception $e) {
                // Do not write to stdout in API requests; keep JSON response clean.
                debugging('MoodleConnect: Failed to finalize execution record: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
            return self::build_response(true, $result['result'] ?? null, null, null, false, $starttime);
        }

        // Action failed, release claim so retry attempts can execute later.
        self::release_claim($claim['recordid']);

        // Action failed.
        return self::build_response(
            false,
            null,
            $result['error'] ?? 'Unknown error',
            $result['error_code'] ?? error_codes::ACTION_FAILED,
            $result['retry'] ?? false,
            $starttime
        );
    }

    /**
     * Build a standardized response array.
     *
     * @param bool $success Whether the action succeeded.
     * @param array|null $result Action-specific result data.
     * @param string|null $error Error message if failed.
     * @param string|null $errorcode Machine-readable error code.
     * @param bool $retry Whether the error is retryable.
     * @param float $starttime Microtime when execution started.
     * @return array Structured response.
     */
    private static function build_response(
        bool $success,
        ?array $result,
        ?string $error,
        ?string $errorcode,
        bool $retry,
        float $starttime
    ): array {
        $executionms = (int) ((microtime(true) - $starttime) * 1000);

        $response = [
            'success' => $success,
            'execution_time_ms' => $executionms,
        ];

        if ($success) {
            $response['result'] = $result;
        } else {
            $response['error'] = $error;
            $response['error_code'] = $errorcode;
            $response['retry'] = $retry;
        }

        return $response;
    }

    /**
     * Atomically claim an execution slot for idempotency.
     *
     * @param object $data Action data.
     * @return array{status: string, recordid: int}
     */
    private static function claim_execution(object $data): array {
        global $DB;

        $userid = self::extract_user_id($data);
        $record = (object) [
            'action_type' => $data->action_type,
            'event_fingerprint' => $data->event_fingerprint,
            'target_id' => null,
            'user_id' => $userid,
            'result' => self::CLAIM_PENDING_SENTINEL,
            'executed_at' => time(),
        ];

        try {
            $recordid = (int) $DB->insert_record('local_mc_plugin_executions', $record, true);
            return ['status' => 'claimed', 'recordid' => $recordid, 'user_id' => $userid];
        } catch (\Exception $e) {
            // Duplicate key means this execution was already claimed/processed.
            $existing = $DB->get_record('local_mc_plugin_executions', [
                'action_type' => $data->action_type,
                'event_fingerprint' => $data->event_fingerprint,
            ], 'id, result, executed_at', IGNORE_MISSING);

            if ($existing) {
                if ($existing->result === self::CLAIM_PENDING_SENTINEL) {
                    return ['status' => 'in_progress', 'recordid' => (int) $existing->id];
                }

                return ['status' => 'already_processed', 'recordid' => (int) $existing->id];
            }
            throw $e;
        }
    }

    /**
     * Finalize the claimed execution record with action-specific result metadata.
     *
     * @param int $recordid Claimed execution record id.
     * @param object $data Action data.
     * @param array $result Handler execution result.
     * @param int|null $userid Pre-extracted user ID from claim phase.
     * @return void
     */
    private static function finalize_execution(int $recordid, object $data, array $result, ?int $userid = null): void {
        global $DB;

        $record = (object) [
            'id' => $recordid,
            'target_id' => self::extract_target_id($data, $result),
            'user_id' => $userid ?? self::extract_user_id($data),
            'result' => json_encode($result['result'] ?? []),
            'executed_at' => time(),
        ];

        $DB->update_record('local_mc_plugin_executions', $record);
    }

    /**
     * Release a claimed execution record when action execution fails.
     *
     * @param int $recordid Claimed execution record id.
     * @return void
     */
    private static function release_claim(int $recordid): void {
        global $DB;

        if ($recordid <= 0) {
            return;
        }

        try {
            $DB->delete_records('local_mc_plugin_executions', ['id' => $recordid]);
        } catch (\Exception $e) {
            debugging('MoodleConnect: Failed to release execution claim: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Extract user id from action payload.
     *
     * @param object $data Action data.
     * @return int|null
     */
    private static function extract_user_id(object $data): ?int {
        $userid = $data->event_payload->user->id ?? null;
        if ($userid === null || $userid === '') {
            return null;
        }
        return (int) $userid;
    }

    /**
     * Extract action target id for execution audit record.
     *
     * @param object $data Action data.
     * @param array $result Handler execution result.
     * @return int|null
     */
    private static function extract_target_id(object $data, array $result): ?int {
        switch ($data->action_type) {
            case 'send_message':
                if (!empty($result['result']['message_id'])) {
                    return (int) $result['result']['message_id'];
                }
                return null;
            case 'issue_certificate':
                return isset($data->action_config->certificate_id) ? (int) $data->action_config->certificate_id : null;
            case 'award_badge':
                return isset($data->action_config->badge_id) ? (int) $data->action_config->badge_id : null;
            case 'enroll_user':
            case 'suspend_enrolment':
                return isset($data->action_config->course_id) ? (int) $data->action_config->course_id : null;
            case 'add_to_group':
                return isset($data->action_config->group_id) ? (int) $data->action_config->group_id : null;
            case 'add_to_cohort':
                return isset($data->action_config->cohort_id) ? (int) $data->action_config->cohort_id : null;
            default:
                return null;
        }
    }
}
