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
 * Unit tests for observer class.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin;

/**
 * Test cases for observer class.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mc_plugin\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * Test that handle_event ignores events not in monitored list.
     */
    public function test_handle_event_ignores_unmonitored_events(): void {
        $this->resetAfterTest(true);

        // Set monitored events to a specific event.
        set_config('monitored_events', '\core\event\user_created', 'local_mc_plugin');
        set_config('site_key', 'test_key', 'local_mc_plugin');
        set_config('site_secret', 'test_secret', 'local_mc_plugin');

        // Create a different event.
        $user = $this->getDataGenerator()->create_user();
        $event = \core\event\user_loggedin::create([
            'userid' => $user->id,
            'context' => \context_system::instance(),
            'other' => ['username' => $user->username],
        ]);

        // Should not throw exception - event is ignored.
        observer::handle_event($event);
        $this->assertTrue(true);
    }

    /**
     * Test that handle_event processes monitored events.
     */
    public function test_handle_event_processes_monitored_events(): void {
        $this->resetAfterTest(true);

        // Set monitored events.
        set_config('monitored_events', '\core\event\user_loggedin', 'local_mc_plugin');
        set_config('site_key', 'test_key', 'local_mc_plugin');
        set_config('site_secret', 'test_secret', 'local_mc_plugin');

        // Create event.
        $user = $this->getDataGenerator()->create_user();
        $event = \core\event\user_loggedin::create([
            'userid' => $user->id,
            'context' => \context_system::instance(),
            'other' => ['username' => $user->username],
        ]);

        // Should not throw exception.
        observer::handle_event($event);
        $this->assertTrue(true);
    }

    /**
     * Test that handle_event works with wildcard.
     */
    public function test_handle_event_with_wildcard(): void {
        $this->resetAfterTest(true);

        // Set wildcard.
        set_config('monitored_events', '*', 'local_mc_plugin');
        set_config('site_key', 'test_key', 'local_mc_plugin');
        set_config('site_secret', 'test_secret', 'local_mc_plugin');

        // Create event.
        $user = $this->getDataGenerator()->create_user();
        $event = \core\event\user_loggedin::create([
            'userid' => $user->id,
            'context' => \context_system::instance(),
            'other' => ['username' => $user->username],
        ]);

        // Should not throw exception.
        observer::handle_event($event);
        $this->assertTrue(true);
    }

    /**
     * Test that handle_event handles missing site_key gracefully.
     */
    public function test_handle_event_missing_site_key(): void {
        $this->resetAfterTest(true);

        // Set monitored events but no site_key.
        set_config('monitored_events', '\core\event\user_loggedin', 'local_mc_plugin');
        set_config('site_key', '', 'local_mc_plugin');

        // Create event.
        $user = $this->getDataGenerator()->create_user();
        $event = \core\event\user_loggedin::create([
            'userid' => $user->id,
            'context' => \context_system::instance(),
            'other' => ['username' => $user->username],
        ]);

        // Should not throw exception.
        observer::handle_event($event);
        $this->assertTrue(true);
    }

    /**
     * Test debug mode logging.
     */
    public function test_handle_event_debug_mode(): void {
        global $CFG;
        $this->resetAfterTest(true);

        // Enable debug mode.
        set_config('debug_mode', 1, 'local_mc_plugin');
        set_config('monitored_events', '\core\event\user_loggedin', 'local_mc_plugin');
        set_config('site_key', 'test_key', 'local_mc_plugin');
        set_config('site_secret', 'test_secret', 'local_mc_plugin');

        // Create event.
        $user = $this->getDataGenerator()->create_user();
        $event = \core\event\user_loggedin::create([
            'userid' => $user->id,
            'context' => \context_system::instance(),
            'other' => ['username' => $user->username],
        ]);

        // Handle event.
        observer::handle_event($event);

        // Check log file exists.
        $logfile = $CFG->dataroot . '/moodleconnect_debug.log';
        $this->assertFileExists($logfile);
    }
}
