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
 * Unit tests for dynamic_inspector class.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local;

/**
 * Test cases for dynamic_inspector class.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mc_plugin\local\dynamic_inspector
 */
class dynamic_inspector_test extends \advanced_testcase {
    /**
     * Test get_event_schema returns correct structure.
     */
    public function test_get_event_schema() {
        $this->resetAfterTest(true);

        $inspector = new dynamic_inspector();
        $schema = $inspector->get_event_schema('\core\event\user_created');

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('event_type', $schema);
        $this->assertArrayHasKey('name', $schema);
        $this->assertArrayHasKey('component', $schema);
        $this->assertArrayHasKey('fields', $schema);
        $this->assertEquals('\core\event\user_created', $schema['event_type']);
        $this->assertIsString($schema['component']);
        $this->assertIsArray($schema['fields']);
        $this->assertNotEmpty($schema['fields']);
    }

    /**
     * Test get_event_schemas with multiple events.
     */
    public function test_get_event_schemas() {
        $this->resetAfterTest(true);

        $inspector = new dynamic_inspector();
        $schemas = $inspector->get_event_schemas([
            '\core\event\user_created',
            '\core\event\user_loggedin',
        ]);

        $this->assertIsArray($schemas);
        $this->assertCount(2, $schemas);
        $this->assertEquals('\core\event\user_created', $schemas[0]['event_type']);
        $this->assertEquals('\core\event\user_loggedin', $schemas[1]['event_type']);
    }

    /**
     * Test extract_data from live event.
     */
    public function test_extract_data() {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user([
            'email' => 'test@example.com',
            'firstname' => 'Test',
            'lastname' => 'User',
        ]);

        $event = \core\event\user_created::create([
            'objectid' => $user->id,
            'context' => \context_user::instance($user->id),
        ]);

        $inspector = new dynamic_inspector();
        $data = $inspector->extract_data($event);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('course', $data);
        $this->assertArrayHasKey('object', $data);
        $this->assertArrayHasKey('event', $data);

        // Check user data.
        $this->assertArrayHasKey('email', $data['user']);
        $this->assertEquals('test@example.com', $data['user']['email']['value']);
        $this->assertEquals('Test', $data['user']['firstname']['value']);
        $this->assertEquals('User', $data['user']['lastname']['value']);
    }

    /**
     * Test get_nested_value retrieves correct values.
     */
    public function test_get_nested_value() {
        $this->resetAfterTest(true);

        $inspector = new dynamic_inspector();
        $data = [
            'user' => [
                'email' => ['value' => 'test@example.com'],
                'firstname' => ['value' => 'Test'],
            ],
            'course' => [
                'fullname' => ['value' => 'Test Course'],
            ],
        ];

        $this->assertEquals('test@example.com', $inspector->get_nested_value($data, 'user.email'));
        $this->assertEquals('Test', $inspector->get_nested_value($data, 'user.firstname'));
        $this->assertEquals('Test Course', $inspector->get_nested_value($data, 'course.fullname'));
        $this->assertNull($inspector->get_nested_value($data, 'user.nonexistent'));
        $this->assertNull($inspector->get_nested_value($data, 'invalid'));
    }

    /**
     * Test get_sample_data returns mock data when no events exist.
     */
    public function test_get_sample_data_mock() {
        $this->resetAfterTest(true);

        $inspector = new dynamic_inspector();
        $data = $inspector->get_sample_data('\core\event\user_created');

        $this->assertIsArray($data);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('course', $data);
        $this->assertArrayHasKey('object', $data);
        $this->assertArrayHasKey('event', $data);
    }

    /**
     * Test extract_data with course event.
     */
    public function test_extract_data_with_course() {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Test Course',
            'shortname' => 'TC1',
        ]);

        $user = $this->getDataGenerator()->create_user();

        $event = \core\event\course_viewed::create([
            'objectid' => $course->id,
            'context' => \context_course::instance($course->id),
            'userid' => $user->id,
        ]);

        $inspector = new dynamic_inspector();
        $data = $inspector->extract_data($event);

        $this->assertArrayHasKey('course', $data);
        $this->assertArrayHasKey('fullname', $data['course']);
        $this->assertEquals('Test Course', $data['course']['fullname']['value']);
        $this->assertEquals('TC1', $data['course']['shortname']['value']);
    }

    /**
     * Test extract_data handles missing user gracefully.
     */
    public function test_extract_data_missing_user() {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();

        $event = \core\event\course_viewed::create([
            'objectid' => $course->id,
            'context' => \context_course::instance($course->id),
            'courseid' => $course->id,
            'userid' => 99999, // Non-existent user.
        ]);

        $inspector = new dynamic_inspector();
        $data = $inspector->extract_data($event);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('user', $data);
        $this->assertEmpty($data['user']);
    }

    /**
     * Test extract_data handles missing course gracefully.
     */
    public function test_extract_data_missing_course() {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Create event with valid course but test that missing course is handled.
        $event = \core\event\user_loggedin::create([
            'objectid' => $user->id,
            'context' => \context_system::instance(),
            'userid' => $user->id,
            'other' => ['username' => $user->username],
        ]);

        $inspector = new dynamic_inspector();
        $data = $inspector->extract_data($event);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('course', $data);
        // System context events don't have course data.
        $this->assertEmpty($data['course']);
    }
}
