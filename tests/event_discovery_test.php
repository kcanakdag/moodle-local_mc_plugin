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
 * Unit tests for event_discovery class.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local;

/**
 * Test cases for event_discovery class.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mc_plugin\local\event_discovery
 */
class event_discovery_test extends \advanced_testcase {
    /**
     * Test get_all_events returns array of events.
     */
    public function test_get_all_events() {
        $this->resetAfterTest(true);

        $discovery = new event_discovery();
        $events = $discovery->get_all_events();

        $this->assertIsArray($events);
        $this->assertNotEmpty($events);

        // Check structure of first event.
        $event = $events[0];
        $this->assertArrayHasKey('class', $event);
        $this->assertArrayHasKey('name', $event);
        $this->assertArrayHasKey('category', $event);
        $this->assertArrayHasKey('component', $event);
        $this->assertArrayHasKey('description', $event);
    }

    /**
     * Test get_events_by_category groups events correctly.
     */
    public function test_get_events_by_category() {
        $this->resetAfterTest(true);

        $discovery = new event_discovery();
        $categorized = $discovery->get_events_by_category();

        $this->assertIsArray($categorized);
        $this->assertNotEmpty($categorized);

        // Check that categories exist.
        foreach ($categorized as $category => $events) {
            $this->assertIsString($category);
            $this->assertIsArray($events);
            $this->assertNotEmpty($events);
        }
    }

    /**
     * Test search_events finds matching events.
     */
    public function test_search_events() {
        $this->resetAfterTest(true);

        $discovery = new event_discovery();
        $results = $discovery->search_events('user');

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);

        // Check that results contain 'user' in searchable fields.
        foreach ($results as $event) {
            $searchable = strtolower(
                $event['name'] . ' ' .
                $event['class'] . ' ' .
                $event['component']
            );
            $this->assertStringContainsString('user', $searchable);
        }
    }

    /**
     * Test search_events with empty query returns all events.
     */
    public function test_search_events_empty_query() {
        $this->resetAfterTest(true);

        $discovery = new event_discovery();
        $all = $discovery->get_all_events();
        $results = $discovery->search_events('');

        $this->assertEquals(count($all), count($results));
    }

    /**
     * Test get_event_info returns correct event.
     */
    public function test_get_event_info() {
        $this->resetAfterTest(true);

        $discovery = new event_discovery();
        $allevents = $discovery->get_all_events();

        // Get first available event to test with.
        $this->assertNotEmpty($allevents);
        $firstevent = $allevents[0];

        $info = $discovery->get_event_info($firstevent['class']);

        $this->assertIsArray($info);
        $this->assertEquals($firstevent['class'], $info['class']);
        $this->assertNotEmpty($info['component']);
    }

    /**
     * Test get_event_info returns null for non-existent event.
     */
    public function test_get_event_info_not_found() {
        $this->resetAfterTest(true);

        $discovery = new event_discovery();
        $info = $discovery->get_event_info('\nonexistent\event\class');

        $this->assertNull($info);
    }

    /**
     * Test get_friendly_name converts class to readable name.
     */
    public function test_get_friendly_name() {
        $name = event_discovery::get_friendly_name('\core\event\user_created');
        $this->assertEquals('User Created', $name);

        $name = event_discovery::get_friendly_name('\mod_forum\event\discussion_created');
        $this->assertEquals('Discussion Created', $name);
    }

    /**
     * Test clear_cache clears the event cache.
     */
    public function test_clear_cache() {
        $this->resetAfterTest(true);

        $discovery = new event_discovery();

        // Populate cache.
        $events1 = $discovery->get_all_events();

        // Clear cache.
        $discovery->clear_cache();

        // Get events again (should re-discover).
        $events2 = $discovery->get_all_events();

        $this->assertEquals(count($events1), count($events2));
    }

    /**
     * Test caching works correctly.
     */
    public function test_caching() {
        $this->resetAfterTest(true);

        $discovery = new event_discovery();

        // First call - populates cache.
        $start = microtime(true);
        $events1 = $discovery->get_all_events();
        $time1 = microtime(true) - $start;

        // Second call - from cache (should be faster).
        $start = microtime(true);
        $events2 = $discovery->get_all_events();
        $time2 = microtime(true) - $start;

        $this->assertEquals(count($events1), count($events2));
        $this->assertLessThan($time1, $time2);
    }
}
