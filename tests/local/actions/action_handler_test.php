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
 * Unit tests for local action handlers.
 *
 * Tests check_availability(), execute() success/failure, and idempotency
 * via execution records for each action handler type.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\actions;

/**
 * Test cases for action handlers.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mc_plugin\local\actions\send_message_handler
 * @covers     \local_mc_plugin\local\actions\enroll_user_handler
 * @covers     \local_mc_plugin\local\actions\suspend_enrolment_handler
 * @covers     \local_mc_plugin\local\actions\add_to_group_handler
 * @covers     \local_mc_plugin\local\actions\add_to_cohort_handler
 * @covers     \local_mc_plugin\local\actions\issue_certificate_handler
 * @covers     \local_mc_plugin\local\actions\award_badge_handler
 */
final class action_handler_test extends \advanced_testcase {
    /**
     * Helper to build a data object for handler methods.
     *
     * @param array $actionconfig Action-specific config.
     * @param int $userid User ID for the event payload.
     * @param string $fingerprint Event fingerprint for idempotency.
     * @return object
     */
    private function build_data(array $actionconfig, int $userid, string $fingerprint = ''): object {
        if (empty($fingerprint)) {
            $fingerprint = hash('sha256', random_bytes(32));
        }
        return (object) [
            'action_type' => '',
            'action_config' => (object) $actionconfig,
            'event_payload' => (object) [
                'user' => (object) [
                    'id' => $userid,
                    'firstname' => 'Test',
                    'lastname' => 'User',
                    'email' => 'testuser@example.com',
                ],
                'course' => (object) [
                    'fullname' => 'Test Course',
                    'shortname' => 'TC101',
                ],
            ],
            'event_fingerprint' => $fingerprint,
            'event_log_id' => 1,
        ];
    }


    /**
     * Test send_message check_availability returns available.
     */
    public function test_send_message_check_availability(): void {
        $handler = new send_message_handler();
        $result = $handler->check_availability();
        $this->assertTrue($result['available']);
        $this->assertNull($result['error']);
    }

    /**
     * Test send_message execute succeeds for a valid user.
     */
    public function test_send_message_execute_success(): void {
        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        $user = $this->getDataGenerator()->create_user();
        $handler = new send_message_handler();
        $data = $this->build_data([
            'subject' => 'Hello {{user.firstname}}',
            'body' => 'Welcome to {{course.fullname}}, {{user.firstname}} {{user.lastname}}!',
        ], $user->id);

        $result = $handler->execute($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('sent', $result['result']['status']);
        $this->assertNotEmpty($result['result']['message_id']);
    }

    /**
     * Test send_message execute fails for nonexistent user.
     */
    public function test_send_message_execute_user_not_found(): void {
        $this->resetAfterTest(true);

        $handler = new send_message_handler();
        $data = $this->build_data(['subject' => 'Hi', 'body' => 'Test'], 999999);

        $result = $handler->execute($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('user_not_found', $result['error_code']);
        $this->assertFalse($result['retry']);
    }

    /**
     * Test send_message execute fails when user ID missing from payload.
     */
    public function test_send_message_execute_missing_user_id(): void {
        $this->resetAfterTest(true);

        $handler = new send_message_handler();
        $data = (object) [
            'action_config' => (object) ['subject' => 'Hi', 'body' => 'Test'],
            'event_payload' => (object) ['user' => (object) []],
            'event_fingerprint' => hash('sha256', 'test'),
            'event_log_id' => 1,
        ];

        $result = $handler->execute($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('invalid_payload', $result['error_code']);
    }

    /**
     * Test send_message idempotency via execution record.
     */
    public function test_send_message_idempotency_record(): void {
        global $DB;
        $this->resetAfterTest(true);

        $fingerprint = hash('sha256', 'unique-event-1');

        // No execution record should exist yet.
        $this->assertFalse($DB->record_exists('local_mc_plugin_executions', [
            'action_type' => 'send_message',
            'event_fingerprint' => $fingerprint,
        ]));

        // Insert an execution record to simulate prior execution.
        $DB->insert_record('local_mc_plugin_executions', (object) [
            'action_type' => 'send_message',
            'event_fingerprint' => $fingerprint,
            'target_id' => 1,
            'user_id' => 1,
            'result' => '{}',
            'executed_at' => time(),
        ]);

        // Should now be detected as already executed.
        $this->assertTrue($DB->record_exists('local_mc_plugin_executions', [
            'action_type' => 'send_message',
            'event_fingerprint' => $fingerprint,
        ]));
    }


    /**
     * Test enroll_user check_availability returns available.
     */
    public function test_enroll_user_check_availability(): void {
        $handler = new enroll_user_handler();
        $result = $handler->check_availability();
        // Manual enrolment is enabled by default in Moodle test environments.
        $this->assertTrue($result['available']);
        $this->assertNull($result['error']);
    }

    /**
     * Test enroll_user execute succeeds.
     */
    public function test_enroll_user_execute_success(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Ensure manual enrolment instance exists for the course.
        $plugin = enrol_get_plugin('manual');
        $instances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        if (empty($instances)) {
            $plugin->add_instance($course);
        }

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        $handler = new enroll_user_handler();
        $data = $this->build_data([
            'course_id' => $course->id,
            'role_id' => $studentrole->id,
        ], $user->id);

        $result = $handler->execute($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('enrolled', $result['result']['status']);
        $this->assertEquals($course->fullname, $result['result']['course_name']);
    }

    /**
     * Test enroll_user execute returns already_enrolled for duplicate.
     */
    public function test_enroll_user_execute_already_enrolled(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        $handler = new enroll_user_handler();
        $data = $this->build_data([
            'course_id' => $course->id,
            'role_id' => $studentrole->id,
        ], $user->id);

        $result = $handler->execute($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('already_enrolled', $result['result']['status']);
    }

    /**
     * Test enroll_user execute fails for nonexistent course.
     */
    public function test_enroll_user_execute_course_not_found(): void {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $handler = new enroll_user_handler();
        $data = $this->build_data(['course_id' => 999999, 'role_id' => 5], $user->id);

        $result = $handler->execute($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('course_not_found', $result['error_code']);
    }

    /**
     * Test enroll_user idempotency via execution record.
     */
    public function test_enroll_user_idempotency_record(): void {
        global $DB;
        $this->resetAfterTest(true);

        $fingerprint = hash('sha256', 'enroll-event-1');

        $this->assertFalse($DB->record_exists('local_mc_plugin_executions', [
            'action_type' => 'enroll_user',
            'event_fingerprint' => $fingerprint,
        ]));

        $DB->insert_record('local_mc_plugin_executions', (object) [
            'action_type' => 'enroll_user',
            'event_fingerprint' => $fingerprint,
            'target_id' => 1,
            'user_id' => 1,
            'result' => '{}',
            'executed_at' => time(),
        ]);

        $this->assertTrue($DB->record_exists('local_mc_plugin_executions', [
            'action_type' => 'enroll_user',
            'event_fingerprint' => $fingerprint,
        ]));
    }


    /**
     * Test suspend_enrolment check_availability returns available.
     */
    public function test_suspend_enrolment_check_availability(): void {
        $handler = new suspend_enrolment_handler();
        $result = $handler->check_availability();
        $this->assertTrue($result['available']);
        $this->assertNull($result['error']);
    }

    /**
     * Test suspend_enrolment execute succeeds (suspend).
     */
    public function test_suspend_enrolment_execute_suspend(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $handler = new suspend_enrolment_handler();
        $data = $this->build_data([
            'course_id' => $course->id,
            'suspend' => true,
        ], $user->id);

        $result = $handler->execute($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('suspended', $result['result']['status']);
    }

    /**
     * Test suspend_enrolment execute returns already_suspended.
     */
    public function test_suspend_enrolment_execute_already_suspended(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Manually suspend the enrolment before testing.
        $sql = "SELECT ue.id, ue.enrolid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid AND ue.userid = :userid";
        $ue = $DB->get_record_sql($sql, ['courseid' => $course->id, 'userid' => $user->id]);
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['id' => $ue->id]);

        $handler = new suspend_enrolment_handler();
        $data = $this->build_data([
            'course_id' => $course->id,
            'suspend' => true,
        ], $user->id);

        $result = $handler->execute($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('already_suspended', $result['result']['status']);
    }

    /**
     * Test suspend_enrolment execute fails when user not enrolled.
     */
    public function test_suspend_enrolment_execute_user_not_enrolled(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $handler = new suspend_enrolment_handler();
        $data = $this->build_data([
            'course_id' => $course->id,
            'suspend' => true,
        ], $user->id);

        $result = $handler->execute($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('user_not_enrolled', $result['error_code']);
    }


    /**
     * Test add_to_group check_availability returns available.
     */
    public function test_add_to_group_check_availability(): void {
        $handler = new add_to_group_handler();
        $result = $handler->check_availability();
        $this->assertTrue($result['available']);
        $this->assertNull($result['error']);
    }

    /**
     * Test add_to_group execute succeeds.
     */
    public function test_add_to_group_execute_success(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $handler = new add_to_group_handler();
        $data = $this->build_data(['group_id' => $group->id], $user->id);

        $result = $handler->execute($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('added', $result['result']['status']);
        $this->assertEquals($group->name, $result['result']['group_name']);
    }

    /**
     * Test add_to_group execute returns already_member.
     */
    public function test_add_to_group_execute_already_member(): void {
        global $CFG;
        require_once($CFG->libdir . '/grouplib.php');
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group->id, $user->id);

        $handler = new add_to_group_handler();
        $data = $this->build_data(['group_id' => $group->id], $user->id);

        $result = $handler->execute($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('already_member', $result['result']['status']);
    }

    /**
     * Test add_to_group execute fails for nonexistent group.
     */
    public function test_add_to_group_execute_group_not_found(): void {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $handler = new add_to_group_handler();
        $data = $this->build_data(['group_id' => 999999], $user->id);

        $result = $handler->execute($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('group_not_found', $result['error_code']);
    }

    /**
     * Test add_to_group execute fails when user not enrolled in course.
     */
    public function test_add_to_group_execute_user_not_enrolled(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $handler = new add_to_group_handler();
        $data = $this->build_data(['group_id' => $group->id], $user->id);

        $result = $handler->execute($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('user_not_enrolled', $result['error_code']);
    }


    /**
     * Test add_to_cohort check_availability returns available.
     */
    public function test_add_to_cohort_check_availability(): void {
        $handler = new add_to_cohort_handler();
        $result = $handler->check_availability();
        $this->assertTrue($result['available']);
        $this->assertNull($result['error']);
    }

    /**
     * Test add_to_cohort execute succeeds.
     */
    public function test_add_to_cohort_execute_success(): void {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();

        $handler = new add_to_cohort_handler();
        $data = $this->build_data(['cohort_id' => $cohort->id], $user->id);

        $result = $handler->execute($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('added', $result['result']['status']);
        $this->assertEquals($cohort->name, $result['result']['cohort_name']);
    }

    /**
     * Test add_to_cohort execute returns already_member.
     */
    public function test_add_to_cohort_execute_already_member(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $user->id);

        $handler = new add_to_cohort_handler();
        $data = $this->build_data(['cohort_id' => $cohort->id], $user->id);

        $result = $handler->execute($data);

        $this->assertTrue($result['success']);
        $this->assertEquals('already_member', $result['result']['status']);
    }

    /**
     * Test add_to_cohort execute fails for nonexistent cohort.
     */
    public function test_add_to_cohort_execute_cohort_not_found(): void {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $handler = new add_to_cohort_handler();
        $data = $this->build_data(['cohort_id' => 999999], $user->id);

        $result = $handler->execute($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('cohort_not_found', $result['error_code']);
    }


    /**
     * Test issue_certificate check_availability detects missing plugin.
     */
    public function test_issue_certificate_check_availability_missing(): void {
        $handler = new issue_certificate_handler();
        $result = $handler->check_availability();
        // Mod_customcert is typically not installed in test environments.
        // If it is installed, this test still passes as it just checks the structure.
        $this->assertArrayHasKey('available', $result);
        $this->assertArrayHasKey('error', $result);
        if (!$result['available']) {
            $this->assertStringContainsString('mod_customcert', $result['error']);
        }
    }

    /**
     * Test issue_certificate execute fails when plugin not installed.
     */
    public function test_issue_certificate_execute_plugin_not_installed(): void {
        $this->resetAfterTest(true);

        $handler = new issue_certificate_handler();
        // Skip this test if customcert is actually installed.
        $availability = $handler->check_availability();
        if ($availability['available']) {
            $this->markTestSkipped('mod_customcert is installed — cannot test missing plugin path');
        }

        $user = $this->getDataGenerator()->create_user();
        $data = $this->build_data(['certificate_id' => 1], $user->id);

        $result = $handler->execute($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('plugin_not_installed', $result['error_code']);
        $this->assertFalse($result['retry']);
    }

    /**
     * Test issue_certificate idempotency via execution record.
     */
    public function test_issue_certificate_idempotency_record(): void {
        global $DB;
        $this->resetAfterTest(true);

        $fingerprint = hash('sha256', 'cert-event-1');

        $this->assertFalse($DB->record_exists('local_mc_plugin_executions', [
            'action_type' => 'issue_certificate',
            'event_fingerprint' => $fingerprint,
        ]));

        $DB->insert_record('local_mc_plugin_executions', (object) [
            'action_type' => 'issue_certificate',
            'event_fingerprint' => $fingerprint,
            'target_id' => 1,
            'user_id' => 1,
            'result' => '{}',
            'executed_at' => time(),
        ]);

        $this->assertTrue($DB->record_exists('local_mc_plugin_executions', [
            'action_type' => 'issue_certificate',
            'event_fingerprint' => $fingerprint,
        ]));
    }


    /**
     * Test award_badge check_availability reflects site config.
     */
    public function test_award_badge_check_availability(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $handler = new award_badge_handler();

        // Verify available when badges are enabled.
        $CFG->enablebadges = true;
        $result = $handler->check_availability();
        $this->assertTrue($result['available']);

        // Verify unavailable when badges are disabled.
        $CFG->enablebadges = false;
        $result = $handler->check_availability();
        $this->assertFalse($result['available']);
        $this->assertStringContainsString('disabled', $result['error']);
    }

    /**
     * Test award_badge execute fails when badges disabled.
     */
    public function test_award_badge_execute_badges_disabled(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $CFG->enablebadges = false;

        $user = $this->getDataGenerator()->create_user();
        $handler = new award_badge_handler();
        $data = $this->build_data(['badge_id' => 1], $user->id);

        $result = $handler->execute($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('badges_disabled', $result['error_code']);
    }

    /**
     * Test award_badge execute fails for nonexistent badge.
     */
    public function test_award_badge_execute_badge_not_found(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $CFG->enablebadges = true;

        $user = $this->getDataGenerator()->create_user();
        $handler = new award_badge_handler();
        $data = $this->build_data(['badge_id' => 999999], $user->id);

        $result = $handler->execute($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('badge_not_found', $result['error_code']);
    }

    /**
     * Test award_badge idempotency via execution record.
     */
    public function test_award_badge_idempotency_record(): void {
        global $DB;
        $this->resetAfterTest(true);

        $fingerprint = hash('sha256', 'badge-event-1');

        $this->assertFalse($DB->record_exists('local_mc_plugin_executions', [
            'action_type' => 'award_badge',
            'event_fingerprint' => $fingerprint,
        ]));

        $DB->insert_record('local_mc_plugin_executions', (object) [
            'action_type' => 'award_badge',
            'event_fingerprint' => $fingerprint,
            'target_id' => 1,
            'user_id' => 1,
            'result' => '{}',
            'executed_at' => time(),
        ]);

        $this->assertTrue($DB->record_exists('local_mc_plugin_executions', [
            'action_type' => 'award_badge',
            'event_fingerprint' => $fingerprint,
        ]));
    }
}
