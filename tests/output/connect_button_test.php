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
 * Tests for connect_button renderable.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\output;

/**
 * Tests for connect_button renderable.
 *
 * After the External Services migration, connect_button carries only
 * apiurl, frontendurl, and isconnected (no sesskey, connecturl, saveurl).
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
     * @return array Test data
     */
    public static function connection_state_provider(): array {
        $testcases = [];

        $connectionstates = [true, false];

        $urlpatterns = [
            ['http://localhost:5000/api', 'http://localhost:5000'],
            ['https://moodleconnect.com/api', 'https://moodleconnect.com'],
            ['http://192.168.1.1:8080/api', 'http://192.168.1.1:8080'],
            ['https://api.example.org/v1', 'https://example.org'],
        ];

        $counter = 0;
        foreach ($connectionstates as $isconnected) {
            foreach ($urlpatterns as $urls) {
                $testcases["case_{$counter}"] = [
                    $isconnected,
                    $urls[0], // API URL.
                    $urls[1], // Frontend URL.
                ];
                $counter++;
            }
        }

        // Add additional variations to exceed 10+ cases.
        for ($i = 0; $i < 20; $i++) {
            $isconnected = ($i % 2 === 0);
            $urlindex = $i % count($urlpatterns);
            $testcases["extra_{$i}"] = [
                $isconnected,
                $urlpatterns[$urlindex][0] . "/v{$i}",
                $urlpatterns[$urlindex][1],
            ];
        }

        return $testcases;
    }

    /**
     * Test that export_for_template returns all required context keys.
     *
     * @dataProvider connection_state_provider
     * @param bool $isconnected Connection state
     * @param string $apiurl API URL
     * @param string $frontendurl Frontend URL
     */
    public function test_property_context_completeness(
        bool $isconnected,
        string $apiurl,
        string $frontendurl
    ): void {
        $this->resetAfterTest(true);

        $button = new connect_button($isconnected, $apiurl, $frontendurl);

        $page = new \moodle_page();
        $renderer = new \renderer_base($page, RENDERER_TARGET_GENERAL);

        $data = $button->export_for_template($renderer);

        $requiredkeys = ['apiurl', 'frontendurl', 'isconnected', 'buttonclass'];

        foreach ($requiredkeys as $key) {
            $this->assertTrue(
                property_exists($data, $key),
                "Context must contain key '{$key}'"
            );
        }

        $this->assertEquals($apiurl, $data->apiurl);
        $this->assertEquals($frontendurl, $data->frontendurl);
        $this->assertEquals($isconnected, $data->isconnected);
    }

    /**
     * Test button class correctness based on connection state.
     *
     * @dataProvider connection_state_provider
     * @param bool $isconnected Connection state
     * @param string $apiurl API URL
     * @param string $frontendurl Frontend URL
     */
    public function test_property_button_class_correctness(
        bool $isconnected,
        string $apiurl,
        string $frontendurl
    ): void {
        $this->resetAfterTest(true);

        $button = new connect_button($isconnected, $apiurl, $frontendurl);

        $page = new \moodle_page();
        $renderer = new \renderer_base($page, RENDERER_TARGET_GENERAL);

        $data = $button->export_for_template($renderer);

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
            'https://api.example.com',
            'https://example.com'
        );

        $config = $button->get_js_config();

        $requiredkeys = ['apiUrl', 'frontendUrl', 'isConnected'];
        foreach ($requiredkeys as $key) {
            $this->assertArrayHasKey($key, $config, "JS config must contain key '{$key}'");
        }

        $this->assertEquals('https://api.example.com', $config['apiUrl']);
        $this->assertEquals('https://example.com', $config['frontendUrl']);
        $this->assertTrue($config['isConnected']);
    }
}
