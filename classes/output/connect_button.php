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
 * Renderable for the connect button component.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\output;

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Renderable for the connect button component.
 *
 * Prepares data for the connect_button Mustache template, including
 * connection state, URLs, and button styling.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connect_button implements renderable, templatable {
    /** @var bool Whether the site is currently connected */
    private $isconnected;

    /** @var string URL to connect.php */
    private $connecturl;

    /** @var string URL to ajax_save.php */
    private $saveurl;

    /** @var string MoodleConnect API URL */
    private $apiurl;

    /** @var string MoodleConnect frontend URL */
    private $frontendurl;

    /** @var string Session key for CSRF protection */
    private $sesskey;

    /**
     * Constructor.
     *
     * @param bool $isconnected Whether the site is currently connected
     * @param string $connecturl URL to connect.php endpoint
     * @param string $saveurl URL to ajax_save.php endpoint
     * @param string $apiurl MoodleConnect API base URL
     * @param string $frontendurl MoodleConnect frontend URL
     * @param string $sesskey Session key for CSRF protection
     */
    public function __construct(
        bool $isconnected,
        string $connecturl,
        string $saveurl,
        string $apiurl,
        string $frontendurl,
        string $sesskey
    ) {
        $this->isconnected = $isconnected;
        $this->connecturl = $connecturl;
        $this->saveurl = $saveurl;
        $this->apiurl = $apiurl;
        $this->frontendurl = $frontendurl;
        $this->sesskey = $sesskey;
    }

    /**
     * Export data for template rendering.
     *
     * @param renderer_base $output The renderer
     * @return stdClass Data for the template
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->connecturl = $this->connecturl;
        $data->saveurl = $this->saveurl;
        $data->apiurl = $this->apiurl;
        $data->frontendurl = $this->frontendurl;
        $data->sesskey = $this->sesskey;
        $data->isconnected = $this->isconnected;
        $data->buttonclass = $this->isconnected ? 'btn-secondary' : 'btn-primary';
        return $data;
    }

    /**
     * Get JavaScript configuration for AMD module initialization.
     *
     * @return array Configuration array for js_call_amd
     */
    public function get_js_config(): array {
        return [
            'connectUrl' => $this->connecturl,
            'saveUrl' => $this->saveurl,
            'apiUrl' => $this->apiurl,
            'frontendUrl' => $this->frontendurl,
            'sesskey' => $this->sesskey,
            'isConnected' => $this->isconnected,
        ];
    }
}
