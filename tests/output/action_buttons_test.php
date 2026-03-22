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
 * Tests for action_buttons renderable.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\output;

/**
 * Tests for action_buttons renderable.
 *
 * After the External Services migration, action_buttons carries no URL or
 * sesskey data. These tests verify the no-arg constructor and empty returns.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mc_plugin\output\action_buttons
 */
final class action_buttons_test extends \advanced_testcase {
    /**
     * Test that export_for_template returns an empty stdClass.
     */
    public function test_export_for_template_returns_empty_object(): void {
        $this->resetAfterTest(true);

        $buttons = new action_buttons();

        $page = new \moodle_page();
        $renderer = new \renderer_base($page, RENDERER_TARGET_GENERAL);

        $data = $buttons->export_for_template($renderer);

        $this->assertInstanceOf(\stdClass::class, $data);
        $this->assertEmpty((array) $data, 'export_for_template must return an empty stdClass');
    }

    /**
     * Test that get_js_config returns an empty array.
     */
    public function test_get_js_config_returns_empty_array(): void {
        $this->resetAfterTest(true);

        $buttons = new action_buttons();

        $config = $buttons->get_js_config();

        $this->assertIsArray($config);
        $this->assertEmpty($config, 'get_js_config must return an empty array');
    }

    /**
     * Test that action_buttons is instantiable with no arguments.
     */
    public function test_no_arg_constructor(): void {
        $this->resetAfterTest(true);

        $buttons = new action_buttons();
        $this->assertInstanceOf(action_buttons::class, $buttons);
    }
}
