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
 * Property tests for connect_button renderable.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\output;

/**
 * Property tests for connect_button renderable.
 *
 * Tests the correctness properties defined in the design document:
 * - Property 1: Connect button context completeness
 * - Property 2: Connect button class correctness
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mc_plugin\output\connect_button
 */
final class connect_button_test extends \advanced_testcase {
    /**
     * Data provider for property tests with various connection states and URLs.
     *
     * Generates 100+ test cases with different combinations of:
     * - Connection state (true/false)
     * - Various URL formats
     * - Different sesskey values
     *
     * @return array Test data
     */
    public static function connection_state_provider(): array {
        $testcases = [];

        // Generate test cases for both connection states.
        $connectionstates = [true, false];

        // Various URL patterns to test.
        $urlpatterns = [
            ['http://localhost:5000/api', 'http://localhost:5000', 'http://localhost/connect.php',
                'http://localhost/ajax_save.php'],
            ['https://moodleconnect.com/api', 'https://moodleconnect.com', 'https://example.com/connect.php',
                'https://example.com/ajax_save.php'],
            ['http://192.168.1.1:8080/api', 'http://192.168.1.1:8080', '/local/mc_plugin/connect.php',
                '/local/mc_plugin/ajax_save.php'],
            ['https://api.example.org/v1', 'https://example.org', '/connect.php', '/ajax_save.php'],
        ];

        // Various sesskey patterns.
        $sesskeys = ['abc123', 'XyZ789AbC', 'a1b2c3d4e5f6', '0123456789'];

        $counter = 0;
        foreach ($connectionstates as $isconnected) {
            foreach ($urlpatterns as $urls) {
                foreach ($sesskeys as $sesskey) {
                    $testcases["case_{$counter}"] = [
                        $isconnected,
                        $urls[2], // Connect URL.
                        $urls[3], // Save URL.
                        $urls[0], // API URL.
                        $urls[1], // Frontend URL.
                        $sesskey,
                    ];
                    $counter++;
                }
            }
        }

        // Add additional random-like variations to reach 100+ cases.
        for ($i = 0; $i < 70; $i++) {
            $isconnected = ($i % 2 === 0);
            $urlindex = $i % count($urlpatterns);
            $sesskeyindex = $i % count($sesskeys);
            $testcases["random_{$i}"] = [
                $isconnected,
                $urlpatterns[$urlindex][2] . "?rand={$i}",
                $urlpatterns[$urlindex][3] . "?rand={$i}",
                $urlpatterns[$urlindex][0],
                $urlpatterns[$urlindex][1],
                $sesskeys[$sesskeyindex] . $i,
            ];
        }

        return $testcases;
    }

    /**
     * **Feature: mustache-templates-refactor, Property 1: Connect button context completeness**
     *
     * *For any* connection state (connected or not connected), the connect_button
     * renderable's export_for_template method SHALL return a context object
     * containing all required keys: connecturl, saveurl, apiurl, frontendurl,
     * sesskey, isconnected, and buttonclass.
     *
     * **Validates: Requirements 2.3**
     *
     * @dataProvider connection_state_provider
     * @param bool $isconnected Connection state
     * @param string $connecturl Connect URL
     * @param string $saveurl Save URL
     * @param string $apiurl API URL
     * @param string $frontendurl Frontend URL
     * @param string $sesskey Session key
     */
    public function test_property_context_completeness(
        bool $isconnected,
        string $connecturl,
        string $saveurl,
        string $apiurl,
        string $frontendurl,
        string $sesskey
    ): void {
        $this->resetAfterTest(true);

        // Create the renderable with the given parameters.
        $button = new connect_button(
            $isconnected,
            $connecturl,
            $saveurl,
            $apiurl,
            $frontendurl,
            $sesskey
        );

        // Create a mock renderer for export_for_template.
        $page = new \moodle_page();
        $renderer = new \renderer_base($page, RENDERER_TARGET_GENERAL);

        // Export the template data.
        $data = $button->export_for_template($renderer);

        // Property 1: All required keys must be present.
        $requiredkeys = ['connecturl', 'saveurl', 'apiurl', 'frontendurl', 'sesskey', 'isconnected', 'buttonclass'];

        foreach ($requiredkeys as $key) {
            $this->assertObjectHasProperty(
                $key,
                $data,
                "Context must contain key '{$key}' for connection state: " . ($isconnected ? 'connected' : 'disconnected')
            );
        }

        // Verify the values match what was passed in.
        $this->assertEquals($connecturl, $data->connecturl);
        $this->assertEquals($saveurl, $data->saveurl);
        $this->assertEquals($apiurl, $data->apiurl);
        $this->assertEquals($frontendurl, $data->frontendurl);
        $this->assertEquals($sesskey, $data->sesskey);
        $this->assertEquals($isconnected, $data->isconnected);
    }

    /**
     * **Feature: mustache-templates-refactor, Property 2: Connect button class correctness**
     *
     * *For any* connection state, the connect_button renderable SHALL return
     * buttonclass='btn-secondary' when isconnected=true, and
     * buttonclass='btn-primary' when isconnected=false.
     *
     * **Validates: Requirements 2.3**
     *
     * @dataProvider connection_state_provider
     * @param bool $isconnected Connection state
     * @param string $connecturl Connect URL
     * @param string $saveurl Save URL
     * @param string $apiurl API URL
     * @param string $frontendurl Frontend URL
     * @param string $sesskey Session key
     */
    public function test_property_button_class_correctness(
        bool $isconnected,
        string $connecturl,
        string $saveurl,
        string $apiurl,
        string $frontendurl,
        string $sesskey
    ): void {
        $this->resetAfterTest(true);

        // Create the renderable with the given parameters.
        $button = new connect_button(
            $isconnected,
            $connecturl,
            $saveurl,
            $apiurl,
            $frontendurl,
            $sesskey
        );

        // Create a mock renderer for export_for_template.
        $page = new \moodle_page();
        $renderer = new \renderer_base($page, RENDERER_TARGET_GENERAL);

        // Export the template data.
        $data = $button->export_for_template($renderer);

        // Property 2: Button class must be correct based on connection state.
        $expectedclass = $isconnected ? 'btn-secondary' : 'btn-primary';
        $this->assertEquals(
            $expectedclass,
            $data->buttonclass,
            "Button class must be '{$expectedclass}' when isconnected=" . ($isconnected ? 'true' : 'false')
        );
    }

    /**
     * Test that get_js_config returns all required configuration keys.
     */
    public function test_get_js_config_completeness(): void {
        $this->resetAfterTest(true);

        $button = new connect_button(
            true,
            '/connect.php',
            '/ajax_save.php',
            'https://api.example.com',
            'https://example.com',
            'testsesskey'
        );

        $config = $button->get_js_config();

        // Verify all required keys are present.
        $requiredkeys = ['connectUrl', 'saveUrl', 'apiUrl', 'frontendUrl', 'sesskey', 'isConnected'];
        foreach ($requiredkeys as $key) {
            $this->assertArrayHasKey($key, $config, "JS config must contain key '{$key}'");
        }

        // Verify values match.
        $this->assertEquals('/connect.php', $config['connectUrl']);
        $this->assertEquals('/ajax_save.php', $config['saveUrl']);
        $this->assertEquals('https://api.example.com', $config['apiUrl']);
        $this->assertEquals('https://example.com', $config['frontendUrl']);
        $this->assertEquals('testsesskey', $config['sesskey']);
        $this->assertTrue($config['isConnected']);
    }
}
