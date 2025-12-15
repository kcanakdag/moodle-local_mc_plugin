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
 * Custom admin setting that displays connection status and connect button.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/mc_plugin/lib.php');

use local_mc_plugin\output\connection_status;

/**
 * Custom admin setting that displays connection status and connect button.
 */
class setting_connection_status extends \admin_setting {
    /** @var string Event input ID for counter refresh */
    private $eventinputid;

    /** @var bool Whether the site is currently connected */
    private $isconnected;

    /**
     * Constructor.
     *
     * @param string $name Unique setting name
     * @param string $eventinputid Event selector input ID (optional)
     * @param bool $isconnected Whether the site is currently connected
     */
    public function __construct($name, $eventinputid = '', $isconnected = false) {
        $this->eventinputid = $eventinputid;
        $this->isconnected = $isconnected;
        parent::__construct($name, get_string('connection_status', 'local_mc_plugin'), '', '');
    }

    /**
     * Returns current value of this setting.
     *
     * @return bool Always returns true (this is a status display, not a stored value)
     */
    public function get_setting() {
        return true;
    }

    /**
     * This setting is not stored, so write does nothing.
     *
     * @param mixed $data Unused
     * @return string Empty string (no error)
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * Returns the HTML for this setting.
     *
     * @param mixed $data Current value
     * @param string $query Search query
     * @return string HTML output
     */
    public function output_html($data, $query = '') {
        global $PAGE;

        // Get the plugin renderer.
        $renderer = $PAGE->get_renderer('local_mc_plugin');

        // Build URLs and config.
        $syncurl = (new \moodle_url('/local/mc_plugin/sync_schema.php'))->out(false);
        $connecturl = (new \moodle_url('/local/mc_plugin/connect.php'))->out(false);
        $saveurl = (new \moodle_url('/local/mc_plugin/ajax_save.php'))->out(false);
        $apiurl = local_mc_plugin_get_api_url();
        $frontendurl = local_mc_plugin_get_frontend_url();
        $sesskey = sesskey();

        // Create the renderable with all data including connect button.
        $status = new connection_status(
            $syncurl,
            $sesskey,
            $this->eventinputid,
            $this->isconnected,
            $connecturl,
            $saveurl,
            $apiurl,
            $frontendurl
        );

        // Render using the Output API.
        $html = $renderer->render($status);

        // Initialize the AMD modules with config from renderable.
        $PAGE->requires->js_call_amd('local_mc_plugin/admin', 'initConnectionStatus', [$status->get_js_config()]);
        $PAGE->requires->js_call_amd('local_mc_plugin/admin', 'initConnect', [$status->get_connect_js_config()]);

        return format_admin_setting($this, $this->visiblename, $html, '', false, '', null, $query);
    }
}
