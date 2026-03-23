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

use local_mc_plugin\output\bulk_sync;

/**
 * Custom admin setting that renders the bulk user sync button with progress.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

        $renderer = $PAGE->get_renderer('local_mc_plugin');
        $bulksync = new bulk_sync();
        $html = $renderer->render($bulksync);

        $PAGE->requires->js_call_amd('local_mc_plugin/admin', 'initBulkSync', []);

        return format_admin_setting($this, '', $html, '', false, '', null, $query);
    }
}
