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
 * Tests for connection_status renderable.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\output;

/**
 * Tests for connection_status renderable.
 *
 * After the External Services migration, connection_status carries only
 * eventinputid, isconnected, apiurl, and frontendurl (no syncurl/sesskey).
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mc_plugin\output\connection_status
 */
final class connection_status_test extends \advanced_testcase {
    /**
     * Data provider for property tests with various configuration states.
     *
     * @return array Test data
     */
    public static function configuration_state_provider(): array {
        $testcases = [];

        $eventinputids = [
            '',
            'id_s_local_mc_plugin_monitored_events',
            'custom_event_input',
            'event-selector-123',
            'mc_events_input',
        ];

        $connectionstates = [true, false];

        $apiurls = [
            'https://moodleconnect.com/api',
            'http://localhost:5000/api',
            'https://staging.moodleconnect.com/api',
        ];

        $counter = 0;
        foreach ($eventinputids as $eventinputid) {
            foreach ($connectionstates as $isconnected) {
                foreach ($apiurls as $apiurl) {
                    $testcases["case_{$counter}"] = [
                        $eventinputid,
                        $isconnected,
                        $apiurl,
                        'https://moodleconnect.com',
                    ];
                    $counter++;
                }
            }
        }

        return $testcases;
    }

    /**
     * Test that export_for_template returns all required context keys.
     *
     * @dataProvider configuration_state_provider
     * @param string $eventinputid Event input ID
     * @param bool $isconnected Connection state
     * @param string $apiurl API URL
     * @param string $frontendurl Frontend URL
     */
    public function test_property_context_completeness(
        string $eventinputid,
        bool $isconnected,
        string $apiurl,
        string $frontendurl
    ): void {
        $this->resetAfterTest(true);

        $status = new connection_status($eventinputid, $isconnected, $apiurl, $frontendurl);

        $page = new \moodle_page();
        $renderer = new \renderer_base($page, RENDERER_TARGET_GENERAL);

        $data = $status->export_for_template($renderer);

        $requiredkeys = ['eventinputid', 'isconnected', 'apiurl', 'frontendurl', 'buttonclass'];

        foreach ($requiredkeys as $key) {
            $this->assertTrue(
                property_exists($data, $key),
                "Context must contain key '{$key}'"
            );
        }

        $this->assertEquals($eventinputid, $data->eventinputid);
        $this->assertEquals($isconnected, $data->isconnected);
        $this->assertEquals($apiurl, $data->apiurl);
        $this->assertEquals($frontendurl, $data->frontendurl);
    }

    /**
     * Test that get_js_config returns only eventInputId.
     */
    public function test_get_js_config_completeness(): void {
        $this->resetAfterTest(true);

        $status = new connection_status(
            'id_s_local_mc_plugin_monitored_events',
            true,
            'https://moodleconnect.com/api',
            'https://moodleconnect.com'
        );

        $config = $status->get_js_config();

        $this->assertArrayHasKey('eventInputId', $config, "JS config must contain 'eventInputId'");
        $this->assertCount(1, $config, 'get_js_config must contain exactly one key');
        $this->assertEquals('id_s_local_mc_plugin_monitored_events', $config['eventInputId']);
    }

    /**
     * Test that get_connect_js_config returns apiUrl, frontendUrl, isConnected.
     */
    public function test_get_connect_js_config_completeness(): void {
        $this->resetAfterTest(true);

        $status = new connection_status(
            '',
            true,
            'https://moodleconnect.com/api',
            'https://moodleconnect.com'
        );

        $config = $status->get_connect_js_config();

        $requiredkeys = ['apiUrl', 'frontendUrl', 'isConnected'];
        foreach ($requiredkeys as $key) {
            $this->assertArrayHasKey($key, $config, "Connect JS config must contain '{$key}'");
        }

        $this->assertEquals('https://moodleconnect.com/api', $config['apiUrl']);
        $this->assertEquals('https://moodleconnect.com', $config['frontendUrl']);
        $this->assertTrue($config['isConnected']);
    }

    /**
     * Test that empty eventinputid is handled correctly.
     */
    public function test_empty_eventinputid(): void {
        $this->resetAfterTest(true);

        $status = new connection_status('', false, '', '');

        $page = new \moodle_page();
        $renderer = new \renderer_base($page, RENDERER_TARGET_GENERAL);

        $data = $status->export_for_template($renderer);

        $this->assertTrue(property_exists($data, 'eventinputid'));
        $this->assertEquals('', $data->eventinputid);
    }

    /**
     * Test default (no-arg) constructor produces a valid object.
     */
    public function test_default_constructor(): void {
        $this->resetAfterTest(true);

        $status = new connection_status();
        $this->assertInstanceOf(connection_status::class, $status);

        $config = $status->get_js_config();
        $this->assertArrayHasKey('eventInputId', $config);
        $this->assertEquals('', $config['eventInputId']);
    }
}
