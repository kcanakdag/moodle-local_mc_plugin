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

use local_mc_plugin\output\action_buttons;

/**
 * Custom admin setting that renders the primary action button.
 */
class setting_action_buttons extends \admin_setting {
    /** @var string URL to the sync_schema.php endpoint */
    private $syncurl;

    /**
     * Constructor.
     *
     * @param string $name Unique setting name
     * @param string $syncurl URL to the schema sync endpoint
     */
    public function __construct($name, $syncurl) {
        $this->syncurl = $syncurl;
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

        // Get the plugin renderer.
        $renderer = $PAGE->get_renderer('local_mc_plugin');

        // Build URLs and config.
        $ajaxsaveurl = (new \moodle_url('/local/mc_plugin/ajax_save.php'))->out(false);
        $sesskey = sesskey();

        // Create the renderable.
        $buttons = new action_buttons(
            $this->syncurl,
            $ajaxsaveurl,
            $sesskey
        );

        // Render using the Output API.
        $html = $renderer->render($buttons);

        // Initialize the AMD module with config from renderable.
        $PAGE->requires->js_call_amd('local_mc_plugin/admin', 'initActionButtons', [$buttons->get_js_config()]);

        return $html;
    }
}
