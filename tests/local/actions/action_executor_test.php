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
 * Unit tests for the action executor.
 *
 * Tests that the executor returns already_processed for duplicate fingerprints,
 * returns errors for unknown action types, and records execution on success.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\actions;

/**
 * Test cases for action_executor.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mc_plugin\local\actions\action_executor
 */
final class action_executor_test extends \advanced_testcase {
    /**
     * Test executor returns error for unknown action type.
     */
    public function test_unknown_action_type(): void {
        $this->resetAfterTest(true);

        $result = action_executor::execute([
            'action_type' => 'nonexistent_action',
            'action_config' => [],
            'event_payload' => [],
            'event_fingerprint' => hash('sha256', 'test-unknown'),
            'event_log_id' => 1,
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('unknown_action_type', $result['error_code']);
        $this->assertArrayHasKey('execution_time_ms', $result);
    }

    /**
     * Test executor returns error when action_type is missing.
     */
    public function test_missing_action_type(): void {
        $this->resetAfterTest(true);

        $result = action_executor::execute([
            'action_config' => [],
            'event_payload' => [],
            'event_fingerprint' => hash('sha256', 'test-missing'),
            'event_log_id' => 1,
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('invalid_request', $result['error_code']);
    }

    /**
     * Test executor returns error when event_fingerprint is missing.
     */
    public function test_missing_fingerprint(): void {
        $this->resetAfterTest(true);

        $result = action_executor::execute([
            'action_type' => 'send_message',
            'action_config' => [],
            'event_payload' => [],
            'event_log_id' => 1,
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('invalid_request', $result['error_code']);
    }

    /**
     * Test executor returns already_processed for duplicate fingerprints.
     */
    public function test_already_processed_duplicate_fingerprint(): void {
        global $DB;
        $this->resetAfterTest(true);

        $fingerprint = hash('sha256', 'duplicate-event-1');

        // Pre-insert an execution record for send_message.
        $DB->insert_record('local_mc_plugin_executions', (object) [
            'action_type' => 'send_message',
            'event_fingerprint' => $fingerprint,
            'target_id' => 1,
            'user_id' => 1,
            'result' => '{"status":"sent"}',
            'executed_at' => time(),
        ]);

        $result = action_executor::execute([
            'action_type' => 'send_message',
            'action_config' => ['subject' => 'Hi', 'body' => 'Test'],
            'event_payload' => ['user' => ['id' => 1]],
            'event_fingerprint' => $fingerprint,
            'event_log_id' => 2,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('already_processed', $result['result']['status']);
        $this->assertArrayHasKey('execution_time_ms', $result);
    }

    /**
     * Test executor returns retryable in-progress status for pending duplicate claim.
     */
    public function test_in_progress_duplicate_claim_is_retryable(): void {
        global $DB;
        $this->resetAfterTest(true);

        $fingerprint = hash('sha256', 'duplicate-in-progress-1');

        $DB->insert_record('local_mc_plugin_executions', (object) [
            'action_type' => 'send_message',
            'event_fingerprint' => $fingerprint,
            'target_id' => null,
            'user_id' => 1,
            'result' => '__pending__',
            'executed_at' => time(),
        ]);

        $result = action_executor::execute([
            'action_type' => 'send_message',
            'action_config' => ['subject' => 'Hi', 'body' => 'Test'],
            'event_payload' => ['user' => ['id' => 1]],
            'event_fingerprint' => $fingerprint,
            'event_log_id' => 20,
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('execution_in_progress', $result['error_code']);
        $this->assertTrue($result['retry']);
    }

    /**
     * Test old pending claim remains in-progress (never auto-reclaimed).
     */
    public function test_old_pending_claim_remains_in_progress(): void {
        global $DB;
        $this->resetAfterTest(true);
        $fingerprint = hash('sha256', 'old-pending-claim-1');

        $DB->insert_record('local_mc_plugin_executions', (object) [
            'action_type' => 'send_message',
            'event_fingerprint' => $fingerprint,
            'target_id' => null,
            'user_id' => 1,
            'result' => '__pending__',
            'executed_at' => time() - 3600,
        ]);

        $result = action_executor::execute([
            'action_type' => 'send_message',
            'action_config' => ['subject' => 'Hi', 'body' => 'Test'],
            'event_payload' => ['user' => ['id' => 1]],
            'event_fingerprint' => $fingerprint,
            'event_log_id' => 21,
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('execution_in_progress', $result['error_code']);
        $this->assertTrue($result['retry']);
    }

    /**
     * Test executor records execution on success.
     */
    public function test_records_execution_on_success(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        $user = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        $fingerprint = hash('sha256', 'cohort-success-1');

        // Verify no execution record exists yet.
        $this->assertFalse($DB->record_exists('local_mc_plugin_executions', [
            'action_type' => 'add_to_cohort',
            'event_fingerprint' => $fingerprint,
        ]));

        $result = action_executor::execute([
            'action_type' => 'add_to_cohort',
            'action_config' => ['cohort_id' => $cohort->id],
            'event_payload' => ['user' => ['id' => $user->id]],
            'event_fingerprint' => $fingerprint,
            'event_log_id' => 3,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('added', $result['result']['status']);

        // Verify execution record was created.
        $this->assertTrue($DB->record_exists('local_mc_plugin_executions', [
            'action_type' => 'add_to_cohort',
            'event_fingerprint' => $fingerprint,
        ]));
    }

    /**
     * Test executor does not record execution on failure.
     */
    public function test_no_record_on_failure(): void {
        global $DB;
        $this->resetAfterTest(true);

        $fingerprint = hash('sha256', 'cohort-fail-1');

        $result = action_executor::execute([
            'action_type' => 'add_to_cohort',
            'action_config' => ['cohort_id' => 999999],
            'event_payload' => ['user' => ['id' => 1]],
            'event_fingerprint' => $fingerprint,
            'event_log_id' => 4,
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('cohort_not_found', $result['error_code']);

        // Verify no execution record was created.
        $this->assertFalse($DB->record_exists('local_mc_plugin_executions', [
            'action_type' => 'add_to_cohort',
            'event_fingerprint' => $fingerprint,
        ]));
    }

    /**
     * Test executor response includes execution_time_ms.
     */
    public function test_response_includes_timing(): void {
        $this->resetAfterTest(true);

        $result = action_executor::execute([
            'action_type' => 'nonexistent_action',
            'action_config' => [],
            'event_payload' => [],
            'event_fingerprint' => hash('sha256', 'timing-test'),
            'event_log_id' => 5,
        ]);

        $this->assertArrayHasKey('execution_time_ms', $result);
        $this->assertIsInt($result['execution_time_ms']);
        $this->assertGreaterThanOrEqual(0, $result['execution_time_ms']);
    }
}
