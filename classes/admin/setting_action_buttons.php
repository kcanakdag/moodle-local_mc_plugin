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
 * Custom admin setting that renders the primary action button.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Custom admin setting that renders the primary action button.
 */
class setting_action_buttons extends \admin_setting {
    /** @var string URL to the sync_schema.php endpoint */
    private $syncurl;

    /** @var string URL to the ajax_save.php endpoint */
    private $ajaxsaveurl;

    /** @var string Session key for CSRF protection */
    private $sesskey;

    /**
     * Constructor.
     *
     * @param string $name Unique setting name
     * @param bool $isconnected Whether the site is currently connected
     * @param string $syncurl URL to the schema sync endpoint
     */
    public function __construct($name, $isconnected, $syncurl) {
        global $CFG;
        $this->syncurl = $syncurl;
        $this->ajaxsaveurl = (new \moodle_url('/local/mc_plugin/ajax_save.php'))->out(false);
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

        $btnlabel = get_string('btn_save_sync', 'local_mc_plugin');

        $html = '
        <div class="form-item row" id="moodleconnect-action-section">
            <div class="form-label col-sm-3"></div>
            <div class="form-setting col-sm-9">
                <div id="moodleconnect-primary-action" style="padding-top: 15px; border-top: 1px solid #dee2e6;">
                    <div id="mc-action-result" style="display: none; padding: 12px; border-radius: 6px;
                        margin-bottom: 15px;"></div>

                    <button type="button" id="mc-primary-btn" class="btn btn-primary"
                        style="padding: 10px 24px; font-size: 15px;">
                        <span id="mc-btn-text">' . s($btnlabel) . '</span>
                        <span id="mc-btn-spinner" style="display: none; margin-left: 8px;">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <style>
        /* Hide Moodle default save button since we handle saving via AJAX */
        #adminsettings .row > .offset-sm-3 > button[type="submit"],
        #adminsettings > .row:last-child,
        form#adminsettings > div.row:has(button[type="submit"]) { display: none !important; }
        </style>';

        // Initialize the AMD module.
        $PAGE->requires->js_call_amd('local_mc_plugin/admin', 'initActionButtons', [[
            'syncUrl' => $this->syncurl,
            'ajaxSaveUrl' => $this->ajaxsaveurl,
            'sesskey' => $this->sesskey,
        ]]);

        return $html;
    }
}
