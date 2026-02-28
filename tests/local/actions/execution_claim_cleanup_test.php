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
 * Tests for pending execution claim cleanup.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\actions;

/**
 * Test cases for execution_claim_cleanup.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mc_plugin\local\actions\execution_claim_cleanup
 */
final class execution_claim_cleanup_test extends \advanced_testcase {
    /**
     * Old pending claims should be returned while recent claims are excluded by age filter.
     */
    public function test_find_pending_claims_respects_age_filter(): void {
        global $DB;
        $this->resetAfterTest(true);

        $oldid = $DB->insert_record('local_mc_plugin_executions', (object) [
            'action_type' => 'send_message',
            'event_fingerprint' => hash('sha256', 'cleanup-old'),
            'target_id' => null,
            'user_id' => 1,
            'result' => '__pending__',
            'executed_at' => time() - 7200,
        ], true);

        $DB->insert_record('local_mc_plugin_executions', (object) [
            'action_type' => 'send_message',
            'event_fingerprint' => hash('sha256', 'cleanup-recent'),
            'target_id' => null,
            'user_id' => 1,
            'result' => '__pending__',
            'executed_at' => time() - 30,
        ], true);

        $DB->insert_record('local_mc_plugin_executions', (object) [
            'action_type' => 'send_message',
            'event_fingerprint' => hash('sha256', 'cleanup-completed'),
            'target_id' => null,
            'user_id' => 1,
            'result' => '{"status":"sent"}',
            'executed_at' => time() - 7200,
        ], true);

        $claims = execution_claim_cleanup::find_pending_claims(3600);

        $this->assertCount(1, $claims);
        $this->assertArrayHasKey($oldid, $claims);
    }

    /**
     * Cleanup with delete=true should remove matched pending claims.
     */
    public function test_cleanup_pending_claims_deletes_records(): void {
        global $DB;
        $this->resetAfterTest(true);

        $claimid = $DB->insert_record('local_mc_plugin_executions', (object) [
            'action_type' => 'add_to_cohort',
            'event_fingerprint' => hash('sha256', 'cleanup-delete'),
            'target_id' => null,
            'user_id' => 1,
            'result' => '__pending__',
            'executed_at' => time() - 90000,
        ], true);

        $result = execution_claim_cleanup::cleanup_pending_claims(86400, true);

        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['deleted']);
        $this->assertFalse($DB->record_exists('local_mc_plugin_executions', ['id' => $claimid]));
    }
}
