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

        $selectedlist = array_map('trim', explode(',', $data));
        $selectedmap = array_flip($selectedlist);

        $discovery = new \local_mc_plugin\local\event_discovery();
        try {
            $events = $discovery->get_all_events();
        } catch (\Exception $e) {
            return $OUTPUT->notification(
                get_string('error_loading_events', 'local_mc_plugin', $e->getMessage()),
                'notifyproblem'
            );
        }

        $grouped = [];
        foreach ($events as $event) {
            $cat = $event['component'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $event;
        }
        ksort($grouped);

        $id = $this->get_id();

        $html = "
        <style>
            .mc-event-selector {
                border: 1px solid #ccc;
                border-radius: 4px;
                max-height: 500px;
                overflow-y: auto;
                padding: 10px;
                background: #fff;
                margin-top: 10px;
            }
            .mc-controls {
                display: flex;
                gap: 10px;
                align-items: center;
            }
            .mc-event-search {
                flex-grow: 1;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .mc-btn-small {
                padding: 5px 10px;
                background: #f0f0f0;
                border: 1px solid #ccc;
                border-radius: 4px;
                cursor: pointer;
                font-size: 0.9em;
            }
            .mc-btn-small:hover {
                background: #e0e0e0;
            }
            .mc-category {
                margin-bottom: 15px;
            }
            .mc-category-title {
                font-weight: bold;
                background: #f5f5f5;
                padding: 5px 10px;
                cursor: pointer;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-radius: 4px;
                user-select: none;
            }
            .mc-category-events {
                padding-left: 10px;
                margin-top: 5px;
            }
            .mc-event-item {
                display: flex;
                align-items: center;
                padding: 2px 0;
            }
            .mc-event-item label {
                margin-left: 8px;
                margin-bottom: 0;
                cursor: pointer;
                font-weight: normal;
            }
            .mc-event-class {
                color: #888;
                font-size: 0.85em;
                margin-left: 5px;
            }
            .mc-hidden {
                display: none !important;
            }
        </style>
        ";

        $html .= '<input type="hidden" name="' . $this->get_full_name() . '" id="' . $id . '" value="' . s($data) . '">';

        $html .= '<div class="mc-event-selector-wrapper">';

        $html .= '<div class="mc-controls">';
        $html .= '<input type="text" id="' . $id . '_search" class="mc-event-search" ' .
            'placeholder="' . get_string('event_search_placeholder', 'local_mc_plugin') . '">';
        $html .= '<span id="' . $id . '_counter" style="font-size:0.9em;color:#666;min-width:100px;">' .
            get_string('event_selected_count', 'local_mc_plugin', 0) . '</span>';
        $html .= '<button type="button" id="' . $id . '_select_visible" class="mc-btn-small">' .
            get_string('event_select_visible', 'local_mc_plugin') . '</button>';
        $html .= '<button type="button" id="' . $id . '_deselect_visible" class="mc-btn-small">' .
            get_string('event_deselect_visible', 'local_mc_plugin') . '</button>';
        $html .= '</div>';

        $html .= '<div class="mc-event-selector">';

        foreach ($grouped as $category => $catevents) {
            $catlabel = ($category === 'core') ? 'Core' : str_replace('_', ' ', $category);
            $catlabel = ucwords($catlabel);

            $html .= '<div class="mc-category">';
            $html .= '<div class="mc-category-title">' . $catlabel .
                ' <small>(' . count($catevents) . ')</small></div>';
            $html .= '<div class="mc-category-events">';

            foreach ($catevents as $event) {
                $checked = isset($selectedmap[$event['class']]) ? 'checked' : '';
                $escapedclass = htmlspecialchars($event['class'], ENT_QUOTES, 'UTF-8');

                $html .= '<div class="mc-event-item">';
                $html .= '<input type="checkbox" class="event-checkbox" data-class="' .
                    $escapedclass . '" ' . $checked . '>';
                $html .= '<label>' . s($event['name']);
                $html .= '<span class="mc-event-class">' . s($event['class']) . '</span>';
                $html .= '</label>';
                $html .= '</div>';
            }

            $html .= '</div></div>';
        }

        $html .= '</div></div>';

        // Initialize the AMD module.
        $PAGE->requires->js_call_amd('local_mc_plugin/admin', 'initEventSelector', [['inputId' => $id]]);

        return format_admin_setting($this, $this->visiblename, $html, $this->description, true, '', '', $query);
    }
}
