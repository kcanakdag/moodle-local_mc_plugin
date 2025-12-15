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
 * Custom admin setting that renders the "Connect to MoodleConnect" button
 * and handles the OAuth-style connection flow with polling.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/mc_plugin/lib.php');

/**
 * Custom admin setting that renders the "Connect to MoodleConnect" button
 * and handles the OAuth-style connection flow with polling.
 */
class setting_connect_button extends \admin_setting {
    /** @var bool Whether the site is currently connected */
    private $isconnected;

    /** @var string URL to the connect.php endpoint */
    private $connecturl;

    /** @var string URL to the ajax_save.php endpoint */
    private $ajaxsaveurl;

    /** @var string MoodleConnect API base URL */
    private $apiurl;

    /** @var string MoodleConnect frontend URL */
    private $frontendurl;

    /** @var string Session key for CSRF protection */
    private $sesskey;

    /**
     * Constructor.
     *
     * @param string $name Unique setting name
     * @param bool $isconnected Whether the site is currently connected
     */
    public function __construct($name, $isconnected) {
        global $CFG;

        $this->isconnected = $isconnected;
        $this->connecturl = (new \moodle_url('/local/mc_plugin/connect.php'))->out(false);
        $this->ajaxsaveurl = (new \moodle_url('/local/mc_plugin/ajax_save.php'))->out(false);
        $this->apiurl = local_mc_plugin_get_api_url();
        $this->frontendurl = local_mc_plugin_get_frontend_url();
        $this->sesskey = sesskey();

        parent::__construct($name, '', '', '');
    }

    /**
     * Returns current value of this setting.
     *
     * @return bool Always returns true (this is a button, not a stored value)
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

        $connectlabel = get_string('connect_button', 'local_mc_plugin');
        $reconnectlabel = get_string('reconnect_button', 'local_mc_plugin');
        $btnlabel = $this->isconnected ? $reconnectlabel : $connectlabel;

        $html = '
        <div class="form-item row" id="moodleconnect-connect-section">
            <div class="form-label col-sm-3">
                <label>' . get_string('connect_heading', 'local_mc_plugin') . '</label>
            </div>
            <div class="form-setting col-sm-9">
                <div id="mc-connect-container">
                    <div id="mc-connect-status" style="display: none; padding: 12px; border-radius: 6px;
                        margin-bottom: 15px;">
                        <span id="mc-connect-status-icon"></span>
                        <span id="mc-connect-status-text"></span>
                    </div>

                    <button type="button" id="mc-connect-btn" class="btn ' .
                        ($this->isconnected ? 'btn-outline-primary' : 'btn-primary') .
                        '" style="padding: 10px 24px; font-size: 15px;">
                        <span id="mc-connect-btn-text">' . s($btnlabel) . '</span>
                        <span id="mc-connect-btn-spinner" style="display: none; margin-left: 8px;">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                        </span>
                    </button>

                    <p class="form-text text-muted" style="margin-top: 8px;">
                        ' . get_string('connect_button_desc', 'local_mc_plugin') . '
                    </p>
                </div>
            </div>
        </div>';

        // Initialize the AMD module.
        $PAGE->requires->js_call_amd('local_mc_plugin/admin', 'initConnect', [[
            'connectUrl' => $this->connecturl,
            'saveUrl' => $this->ajaxsaveurl,
            'apiUrl' => $this->apiurl,
            'frontendUrl' => $this->frontendurl,
            'sesskey' => $this->sesskey,
            'isConnected' => $this->isconnected,
        ]]);

        return $html;
    }
}
