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
 * Property tests for action_buttons renderable.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\output;

/**
 * Property tests for action_buttons renderable.
 *
 * Tests the correctness property defined in the design document:
 * - Property 6: Action buttons context completeness
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mc_plugin\output\action_buttons
 */
final class action_buttons_test extends \advanced_testcase {
    /**
     * Data provider for property tests with various configuration states.
     *
     * Generates 100+ test cases with different combinations of:
     * - Various sync URL formats
     * - Various AJAX save URL formats
     * - Different sesskey values
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
            'https://moodle.example.org/local/mc_plugin/sync_schema.php',
        ];

        // Various AJAX save URL patterns.
        $ajaxsaveurls = [
            '/local/mc_plugin/ajax_save.php',
            'http://localhost/local/mc_plugin/ajax_save.php',
            'https://example.com/local/mc_plugin/ajax_save.php',
            '/ajax_save.php',
            'https://moodle.example.org/local/mc_plugin/ajax_save.php',
        ];

        // Various sesskey patterns.
        $sesskeys = ['abc123', 'XyZ789AbC', 'a1b2c3d4e5f6', '0123456789', 'session_key_test'];

        $counter = 0;
        foreach ($syncurls as $syncurl) {
            foreach ($ajaxsaveurls as $ajaxsaveurl) {
                foreach ($sesskeys as $sesskey) {
                    $testcases["case_{$counter}"] = [
                        $syncurl,
                        $ajaxsaveurl,
                        $sesskey,
                    ];
                    $counter++;
                }
            }
        }

        // We should have 5 * 5 * 5 = 125 test cases, which exceeds 100.
        return $testcases;
    }

    /**
     * **Feature: mustache-templates-refactor, Property 6: Action buttons context completeness**
     *
     * *For any* configuration, the action_buttons renderable's export_for_template
     * method SHALL return a context object containing all required keys:
     * syncurl, ajaxsaveurl, and sesskey.
     *
     * **Validates: Requirements 2.6**
     *
     * @dataProvider configuration_state_provider
     * @param string $syncurl Sync URL
     * @param string $ajaxsaveurl AJAX save URL
     * @param string $sesskey Session key
     */
    public function test_property_context_completeness(
        string $syncurl,
        string $ajaxsaveurl,
        string $sesskey
    ): void {
        $this->resetAfterTest(true);

        // Create the renderable with the given parameters.
        $buttons = new action_buttons(
            $syncurl,
            $ajaxsaveurl,
            $sesskey
        );

        // Create a mock renderer for export_for_template.
        $page = new \moodle_page();
        $renderer = new \renderer_base($page, RENDERER_TARGET_GENERAL);

        // Export the template data.
        $data = $buttons->export_for_template($renderer);

        // Property 6: All required keys must be present.
        $requiredkeys = ['syncurl', 'ajaxsaveurl', 'sesskey'];

        foreach ($requiredkeys as $key) {
            $this->assertObjectHasProperty(
                $key,
                $data,
                "Context must contain key '{$key}'"
            );
        }

        // Verify the values match what was passed in.
        $this->assertEquals($syncurl, $data->syncurl);
        $this->assertEquals($ajaxsaveurl, $data->ajaxsaveurl);
        $this->assertEquals($sesskey, $data->sesskey);
    }

    /**
     * Test that get_js_config returns all required configuration keys.
     */
    public function test_get_js_config_completeness(): void {
        $this->resetAfterTest(true);

        $buttons = new action_buttons(
            '/local/mc_plugin/sync_schema.php',
            '/local/mc_plugin/ajax_save.php',
            'testsesskey'
        );

        $config = $buttons->get_js_config();

        // Verify all required keys are present.
        $requiredkeys = ['syncUrl', 'ajaxSaveUrl', 'sesskey'];
        foreach ($requiredkeys as $key) {
            $this->assertArrayHasKey($key, $config, "JS config must contain key '{$key}'");
        }

        // Verify values match.
        $this->assertEquals('/local/mc_plugin/sync_schema.php', $config['syncUrl']);
        $this->assertEquals('/local/mc_plugin/ajax_save.php', $config['ajaxSaveUrl']);
        $this->assertEquals('testsesskey', $config['sesskey']);
    }

    /**
     * Test that URLs with query parameters are preserved.
     */
    public function test_urls_with_query_parameters(): void {
        $this->resetAfterTest(true);

        $syncurl = '/sync_schema.php?param1=value1&param2=value2';
        $ajaxsaveurl = '/ajax_save.php?action=save&format=json';

        $buttons = new action_buttons(
            $syncurl,
            $ajaxsaveurl,
            'sesskey123'
        );

        $page = new \moodle_page();
        $renderer = new \renderer_base($page, RENDERER_TARGET_GENERAL);

        $data = $buttons->export_for_template($renderer);

        $this->assertEquals($syncurl, $data->syncurl);
        $this->assertEquals($ajaxsaveurl, $data->ajaxsaveurl);
    }
}
