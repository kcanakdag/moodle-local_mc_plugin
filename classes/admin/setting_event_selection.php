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
 * Custom admin setting for event selection with search and filtering.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

use local_mc_plugin\output\event_selector;

/**
 * Custom admin setting for event selection with search and filtering.
 *
 * Provides an interactive UI for selecting which Moodle events to monitor,
 * with search, category filtering, and bulk selection capabilities.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_event_selection extends \admin_setting_configtext {
    /**
     * Constructor.
     *
     * @param string $name Unique setting name
     * @param string $visiblename Localised label
     * @param string $description Localised description
     * @param mixed $defaultsetting Default value
     */
    public function __construct($name, $visiblename, $description, $defaultsetting) {
        parent::__construct($name, $visiblename, $description, $defaultsetting, PARAM_RAW);
    }

    /**
     * Return the HTML for this setting.
     *
     * @param mixed $data Current value
     * @param string $query Search query
     * @return string HTML output
     */
    public function output_html($data, $query = '') {
        global $OUTPUT, $PAGE;

        // Get selected events from current value.
        $selectedlist = array_filter(array_map('trim', explode(',', $data)));

        // Discover available events.
        $discovery = new \local_mc_plugin\local\event_discovery();
        try {
            $events = $discovery->get_all_events();
        } catch (\Exception $e) {
            return $OUTPUT->notification(
                get_string('error_loading_events', 'local_mc_plugin', $e->getMessage()),
                'notifyproblem'
            );
        }

        // Get the plugin renderer.
        $renderer = $PAGE->get_renderer('local_mc_plugin');

        // Create the renderable.
        $selector = new event_selector(
            $this->get_id(),
            $this->get_full_name(),
            $events,
            $selectedlist
        );

        // Render using the Output API.
        $html = $renderer->render($selector);

        // Initialize the AMD module with config from renderable.
        $PAGE->requires->js_call_amd('local_mc_plugin/admin', 'initEventSelector', [$selector->get_js_config()]);

        return format_admin_setting($this, $this->visiblename, $html, $this->description, true, '', '', $query);
    }
}
