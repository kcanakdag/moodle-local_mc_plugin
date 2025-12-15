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
 * Custom admin setting that displays connection and sync status.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Custom admin setting that displays connection and sync status.
 */
class setting_connection_status extends \admin_setting {
    /** @var bool Whether the site is currently connected */
    private $isconnected;

    /** @var string Session key for CSRF protection */
    private $sesskey;

    /** @var string Event input ID for counter refresh */
    private $eventinputid;

    /**
     * Constructor.
     *
     * @param string $name Unique setting name
     * @param bool $isconnected Whether the site is currently connected
     * @param string $eventinputid Event selector input ID (optional)
     */
    public function __construct($name, $isconnected, $eventinputid = '') {
        $this->isconnected = $isconnected;
        $this->sesskey = sesskey();
        $this->eventinputid = $eventinputid;
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

        $syncurl = (new \moodle_url('/local/mc_plugin/sync_schema.php'))->out(false);

        // Build the status display HTML.
        $html = '<div id="mc-connection-status">';
        $html .= '<div id="mc-status-display">';
        $html .= '<span id="mc-status-dot" style="color: #6c757d; margin-right: 6px;">●</span>';
        $html .= '<span id="mc-status-text" style="color: #6c757d; font-weight: 500;">Not configured</span>';
        $html .= '<span id="mc-site-name" style="margin-left: 8px; color: #666;"></span>';
        $html .= '<span id="mc-sync-status" style="margin-left: 12px; font-size: 0.9em; color: #666;"></span>';
        $html .= '</div>';
        $html .= '<span id="mc-test-result" style="margin-left: 10px; font-size: 0.85em;"></span>';
        $html .= '</div>';

        // Initialize the AMD module.
        $PAGE->requires->js_call_amd('local_mc_plugin/admin', 'initConnectionStatus', [[
            'syncUrl' => $syncurl,
            'sesskey' => $this->sesskey,
            'eventInputId' => $this->eventinputid,
        ]]);

        return format_admin_setting($this, $this->visiblename, $html, '', false, '', null, $query);
    }
}
