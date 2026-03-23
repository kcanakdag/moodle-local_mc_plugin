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
 * Renderable for the connection status component.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\output;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Renderable for the connection status component.
 *
 * Prepares data for the connection_status Mustache template, including
 * event input ID, connection state, and connect button configuration.
 * URL plumbing removed: JS routes via core/ajax methodnames instead of URLs.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connection_status implements renderable, templatable {
    /** @var string Event selector input ID for counter refresh */
    private $eventinputid;

    /** @var bool Whether the site is currently connected */
    private $isconnected;

    /** @var string MoodleConnect API URL */
    private $apiurl;

    /** @var string MoodleConnect frontend URL */
    private $frontendurl;

    /**
     * Constructor.
     *
     * @param string $eventinputid Event selector input ID (optional)
     * @param bool $isconnected Whether the site is currently connected
     * @param string $apiurl MoodleConnect API base URL
     * @param string $frontendurl MoodleConnect frontend URL
     */
    public function __construct(
        string $eventinputid = '',
        bool $isconnected = false,
        string $apiurl = '',
        string $frontendurl = ''
    ) {
        $this->eventinputid = $eventinputid;
        $this->isconnected = $isconnected;
        $this->apiurl = $apiurl;
        $this->frontendurl = $frontendurl;
    }

    /**
     * Export data for template rendering.
     *
     * @param renderer_base $output The renderer
     * @return stdClass Data for the template
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->eventinputid = $this->eventinputid;
        // Connect button data.
        $data->isconnected = $this->isconnected;
        $data->apiurl = $this->apiurl;
        $data->frontendurl = $this->frontendurl;
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
            'eventInputId' => $this->eventinputid,
        ];
    }

    /**
     * Get JavaScript configuration for connect button AMD module.
     *
     * @return array Configuration array for js_call_amd
     */
    public function get_connect_js_config(): array {
        return [
            'apiUrl' => $this->apiurl,
            'frontendUrl' => $this->frontendurl,
            'isConnected' => $this->isconnected,
        ];
    }
}
