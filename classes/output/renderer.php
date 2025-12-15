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
 * Renderer for the MoodleConnect plugin.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\output;

/**
 * Renderer for the MoodleConnect plugin.
 *
 * Provides render methods for each UI component, converting renderables
 * to HTML using Mustache templates.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the connect button.
     *
     * @param connect_button $button The renderable
     * @return string HTML output
     */
    protected function render_connect_button(connect_button $button): string {
        $data = $button->export_for_template($this);
        return $this->render_from_template('local_mc_plugin/connect_button', $data);
    }

    /**
     * Render the connection status display.
     *
     * @param connection_status $status The renderable
     * @return string HTML output
     */
    protected function render_connection_status(connection_status $status): string {
        $data = $status->export_for_template($this);
        return $this->render_from_template('local_mc_plugin/connection_status', $data);
    }

    /**
     * Render the event selector.
     *
     * @param event_selector $selector The renderable
     * @return string HTML output
     */
    protected function render_event_selector(event_selector $selector): string {
        $data = $selector->export_for_template($this);
        return $this->render_from_template('local_mc_plugin/event_selector', $data);
    }

    /**
     * Render the action buttons.
     *
     * @param action_buttons $buttons The renderable
     * @return string HTML output
     */
    protected function render_action_buttons(action_buttons $buttons): string {
        $data = $buttons->export_for_template($this);
        return $this->render_from_template('local_mc_plugin/action_buttons', $data);
    }
}
