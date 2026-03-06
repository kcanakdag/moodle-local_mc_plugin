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
 * Custom admin setting that renders the bulk sync button.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Custom admin setting that renders the bulk user sync button with progress.
 */
class setting_bulk_sync extends \admin_setting {
    /**
     * Constructor.
     *
     * @param string $name Unique setting name
     */
    public function __construct($name) {
        parent::__construct($name, '', '', '');
        $this->nosave = true;
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

        $syncurl = (new \moodle_url('/local/mc_plugin/sync_schema.php'))->out(false);
        $sesskey = sesskey();

        $html = '<div id="mc-bulk-sync">';

        // Description.
        $html .= '<p class="text-muted small">'
            . get_string('bulk_sync_desc', 'local_mc_plugin') . '</p>';

        // Status area (populated by JS).
        $html .= '<div id="mc-bulk-sync-status" class="mb-2 small"></div>';

        // Button.
        $html .= '<button type="button" id="mc-bulk-sync-btn" class="btn btn-outline-secondary" disabled>';
        $html .= '<span id="mc-bulk-sync-spinner" class="spinner-border spinner-border-sm d-none mr-1"'
            . ' role="status" aria-hidden="true"></span>';
        $html .= '<span id="mc-bulk-sync-btn-text">'
            . get_string('bulk_sync_button', 'local_mc_plugin') . '</span>';
        $html .= '</button>';

        // Progress bar (hidden initially).
        $html .= '<div id="mc-bulk-sync-progress" class="mt-2 d-none" style="max-width: 400px;">';
        $html .= '<div class="progress" style="height: 20px;">';
        $html .= '<div id="mc-bulk-sync-bar" class="progress-bar progress-bar-striped progress-bar-animated"'
            . ' role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>';
        $html .= '</div>';
        $html .= '<div id="mc-bulk-sync-progress-text" class="small text-muted mt-1"></div>';
        $html .= '</div>';

        // Result area.
        $html .= '<div id="mc-bulk-sync-result" class="mt-2"></div>';

        $html .= '</div>';

        // Initialize the AMD module.
        $PAGE->requires->js_call_amd('local_mc_plugin/admin', 'initBulkSync', [[
            'syncUrl' => $syncurl,
            'sesskey' => $sesskey,
        ]]);

        return format_admin_setting($this, '', $html, '', false, '', null, $query);
    }
}
