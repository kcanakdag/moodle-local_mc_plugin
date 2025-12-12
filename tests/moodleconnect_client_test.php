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
 * Unit tests for moodleconnect_client class.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local;

/**
 * Test cases for moodleconnect_client class.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mc_plugin\local\moodleconnect_client
 */
class moodleconnect_client_test extends \advanced_testcase {

    /**
     * Test send_event returns error when site_key is missing.
     */
    public function test_send_event_missing_site_key() {
        $this->resetAfterTest(true);

        unset_config('site_key', 'local_mc_plugin');
        set_config('site_secret', 'test_secret', 'local_mc_plugin');

        $result = moodleconnect_client::send_event('\core\event\user_created', ['test' => 'data']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertFalse($result['success']);
    }

    /**
     * Test send_event returns error when site_secret is missing.
     */
    public function test_send_event_missing_site_secret() {
        $this->resetAfterTest(true);

        set_config('site_key', 'test_key', 'local_mc_plugin');
        unset_config('site_secret', 'local_mc_plugin');

        $result = moodleconnect_client::send_event('\core\event\user_created', ['test' => 'data']);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
    }

    /**
     * Test sync_schema returns error when site_key is missing.
     */
    public function test_sync_schema_missing_site_key() {
        $this->resetAfterTest(true);

        unset_config('site_key', 'local_mc_plugin');
        set_config('site_secret', 'test_secret', 'local_mc_plugin');

        $result = moodleconnect_client::sync_schema([]);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
    }

    /**
     * Test sync_schema returns error when site_secret is missing.
     */
    public function test_sync_schema_missing_site_secret() {
        $this->resetAfterTest(true);

        set_config('site_key', 'test_key', 'local_mc_plugin');
        unset_config('site_secret', 'local_mc_plugin');

        $result = moodleconnect_client::sync_schema([]);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
    }

    /**
     * Test send_event with valid configuration.
     */
    public function test_send_event_with_valid_config() {
        $this->resetAfterTest(true);

        set_config('site_key', 'test_key', 'local_mc_plugin');
        set_config('site_secret', 'test_secret', 'local_mc_plugin');

        $result = moodleconnect_client::send_event('\core\event\user_created', [
            'user' => ['email' => 'test@example.com'],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }

    /**
     * Test sync_schema with valid configuration.
     */
    public function test_sync_schema_with_valid_config() {
        $this->resetAfterTest(true);

        set_config('site_key', 'test_key', 'local_mc_plugin');
        set_config('site_secret', 'test_secret', 'local_mc_plugin');

        $events = [
            [
                'event_type' => '\core\event\user_created',
                'name' => 'User Created',
                'component' => 'core',
                'fields' => ['user.email', 'user.firstname'],
            ],
        ];

        $result = moodleconnect_client::sync_schema($events);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }
}
