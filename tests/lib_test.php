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
 * Unit tests for lib.php functions.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin;

// Require lib.php to load the functions being tested.
require_once(__DIR__ . '/../lib.php');

/**
 * Test cases for lib.php functions.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_mc_plugin_get_api_url
 * @covers     ::local_mc_plugin_get_frontend_url
 */
final class lib_test extends \advanced_testcase {
    /**
     * Test get_api_url returns default production URL.
     */
    public function test_get_api_url_default(): void {
        global $CFG;
        $this->resetAfterTest(true);

        unset($CFG->local_mc_plugin_moodleconnect_url);

        $url = \local_mc_plugin_get_api_url();
        $this->assertEquals('https://moodleconnect.com/api', $url);
    }

    /**
     * Test get_api_url returns custom URL from config.
     */
    public function test_get_api_url_custom(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $CFG->local_mc_plugin_moodleconnect_url = 'http://localhost:5000/api';

        $url = \local_mc_plugin_get_api_url();
        $this->assertEquals('http://localhost:5000/api', $url);
    }

    /**
     * Test get_api_url strips trailing slash.
     */
    public function test_get_api_url_strips_trailing_slash(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $CFG->local_mc_plugin_moodleconnect_url = 'http://localhost:5000/api/';

        $url = \local_mc_plugin_get_api_url();
        $this->assertEquals('http://localhost:5000/api', $url);
    }

    /**
     * Test get_frontend_url derives from API URL.
     */
    public function test_get_frontend_url_derived(): void {
        global $CFG;
        $this->resetAfterTest(true);

        unset($CFG->local_mc_plugin_moodleconnect_url);
        unset($CFG->local_mc_plugin_moodleconnect_frontend_url);

        $url = \local_mc_plugin_get_frontend_url();
        $this->assertEquals('https://moodleconnect.com', $url);
    }

    /**
     * Test get_frontend_url returns custom URL from config.
     */
    public function test_get_frontend_url_custom(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $CFG->local_mc_plugin_moodleconnect_frontend_url = 'http://localhost:5173';

        $url = \local_mc_plugin_get_frontend_url();
        $this->assertEquals('http://localhost:5173', $url);
    }

    /**
     * Test get_frontend_url strips trailing slash.
     */
    public function test_get_frontend_url_strips_trailing_slash(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $CFG->local_mc_plugin_moodleconnect_frontend_url = 'http://localhost:5173/';

        $url = \local_mc_plugin_get_frontend_url();
        $this->assertEquals('http://localhost:5173', $url);
    }

    /**
     * Test get_frontend_url derives correctly from custom API URL.
     */
    public function test_get_frontend_url_derived_from_custom_api(): void {
        global $CFG;
        $this->resetAfterTest(true);

        $CFG->local_mc_plugin_moodleconnect_url = 'http://localhost:5000/api';
        unset($CFG->local_mc_plugin_moodleconnect_frontend_url);

        $url = \local_mc_plugin_get_frontend_url();
        $this->assertEquals('http://localhost:5000', $url);
    }
}
