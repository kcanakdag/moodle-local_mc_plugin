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
 * Property tests for connection_status renderable.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\output;

/**
 * Property tests for connection_status renderable.
 *
 * Tests the correctness property defined in the design document:
 * - Property 3: Connection status context completeness
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
     * Generates 100+ test cases with different combinations of:
     * - Various sync URL formats
     * - Different sesskey values
     * - Various event input ID formats (including empty)
     *
     * @return array Test data
     */
    public static function configuration_state_provider(): array {
        $testcases = [];

        // Various sync URL patterns.
        $syncurls = [
            '/local/mc_plugin/sync_schema.php',
            'http://localhost/local/mc_plugin/sync_schema.php',
            'https://example.com/local/mc_plugin/sync_schema.php',
            '/sync_schema.php',
        ];

        // Various sesskey patterns.
        $sesskeys = ['abc123', 'XyZ789AbC', 'a1b2c3d4e5f6', '0123456789', 'session_key_test'];

        // Various event input ID patterns (including empty).
        $eventinputids = [
            '',
            'id_s_local_mc_plugin_monitored_events',
            'custom_event_input',
            'event-selector-123',
            'mc_events_input',
        ];

        $counter = 0;
        foreach ($syncurls as $syncurl) {
            foreach ($sesskeys as $sesskey) {
                foreach ($eventinputids as $eventinputid) {
                    $testcases["case_{$counter}"] = [
                        $syncurl,
                        $sesskey,
                        $eventinputid,
                    ];
                    $counter++;
                }
            }
        }

        // Add additional variations to ensure we have 100+ cases.
        for ($i = 0; $i < 20; $i++) {
            $syncurlindex = $i % count($syncurls);
            $sesskeyindex = $i % count($sesskeys);
            $eventinputidindex = $i % count($eventinputids);
            $testcases["extra_{$i}"] = [
                $syncurls[$syncurlindex] . "?v={$i}",
                $sesskeys[$sesskeyindex] . $i,
                $eventinputids[$eventinputidindex] . ($eventinputids[$eventinputidindex] ? "_{$i}" : ''),
            ];
        }

        return $testcases;
    }

    /**
     * **Feature: mustache-templates-refactor, Property 3: Connection status context completeness**
     *
     * *For any* configuration state, the connection_status renderable's
     * export_for_template method SHALL return a context object containing
     * all required keys: syncurl, sesskey, and eventinputid.
     *
     * **Validates: Requirements 2.4**
     *
     * @dataProvider configuration_state_provider
     * @param string $syncurl Sync URL
     * @param string $sesskey Session key
     * @param string $eventinputid Event input ID
     */
    public function test_property_context_completeness(
        string $syncurl,
        string $sesskey,
        string $eventinputid
    ): void {
        $this->resetAfterTest(true);

        // Create the renderable with the given parameters.
        $status = new connection_status(
            $syncurl,
            $sesskey,
            $eventinputid
        );

        // Create a mock renderer for export_for_template.
        $page = new \moodle_page();
        $renderer = new \renderer_base($page, RENDERER_TARGET_GENERAL);

        // Export the template data.
        $data = $status->export_for_template($renderer);

        // Property 3: All required keys must be present.
        $requiredkeys = ['syncurl', 'sesskey', 'eventinputid'];

        foreach ($requiredkeys as $key) {
            $this->assertObjectHasProperty(
                $key,
                $data,
                "Context must contain key '{$key}'"
            );
        }

        // Verify the values match what was passed in.
        $this->assertEquals($syncurl, $data->syncurl);
        $this->assertEquals($sesskey, $data->sesskey);
        $this->assertEquals($eventinputid, $data->eventinputid);
    }

    /**
     * Test that get_js_config returns all required configuration keys.
     */
    public function test_get_js_config_completeness(): void {
        $this->resetAfterTest(true);

        $status = new connection_status(
            '/local/mc_plugin/sync_schema.php',
            'testsesskey',
            'id_s_local_mc_plugin_monitored_events'
        );

        $config = $status->get_js_config();

        // Verify all required keys are present.
        $requiredkeys = ['syncUrl', 'sesskey', 'eventInputId'];
        foreach ($requiredkeys as $key) {
            $this->assertArrayHasKey($key, $config, "JS config must contain key '{$key}'");
        }

        // Verify values match.
        $this->assertEquals('/local/mc_plugin/sync_schema.php', $config['syncUrl']);
        $this->assertEquals('testsesskey', $config['sesskey']);
        $this->assertEquals('id_s_local_mc_plugin_monitored_events', $config['eventInputId']);
    }

    /**
     * Test that empty eventinputid is handled correctly.
     */
    public function test_empty_eventinputid(): void {
        $this->resetAfterTest(true);

        $status = new connection_status(
            '/sync_schema.php',
            'sesskey123',
            ''
        );

        $page = new \moodle_page();
        $renderer = new \renderer_base($page, RENDERER_TARGET_GENERAL);

        $data = $status->export_for_template($renderer);

        $this->assertObjectHasProperty('eventinputid', $data);
        $this->assertEquals('', $data->eventinputid);
    }
}
