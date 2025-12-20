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
 * Renderable for the event selector component.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\output;

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Renderable for the event selector component.
 *
 * Prepares data for the event_selector Mustache template, including
 * grouped events by category and selection state.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_selector implements renderable, templatable {
    /** @var string Input element ID */
    private $inputid;

    /** @var string Input element name */
    private $inputname;

    /** @var array List of all available events */
    private $events;

    /** @var array List of selected event class names */
    private $selectedevents;

    /**
     * Constructor.
     *
     * @param string $inputid Input element ID
     * @param string $inputname Input element name
     * @param array $events List of events with 'class', 'name', 'component' keys
     * @param array $selectedevents List of selected event class names
     */
    public function __construct(
        string $inputid,
        string $inputname,
        array $events,
        array $selectedevents
    ) {
        $this->inputid = $inputid;
        $this->inputname = $inputname;
        $this->events = $events;
        $this->selectedevents = array_flip($selectedevents);
    }

    /**
     * Export data for template rendering.
     *
     * @param renderer_base $output The renderer
     * @return stdClass Data for the template
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->inputid = $this->inputid;
        $data->inputname = $this->inputname;
        $data->currentvalue = implode(',', array_keys($this->selectedevents));

        // Group events by component.
        $grouped = [];
        foreach ($this->events as $event) {
            $component = $event['component'];
            if (!isset($grouped[$component])) {
                $grouped[$component] = [];
            }
            $grouped[$component][] = $event;
        }

        // Sort categories alphabetically.
        ksort($grouped);

        // Build categories array for template.
        $categories = [];
        foreach ($grouped as $component => $componentevents) {
            $category = new stdClass();
            $category->name = $this->format_category_name($component);
            $category->count = count($componentevents);

            $events = [];
            foreach ($componentevents as $event) {
                $eventobj = new stdClass();
                $eventobj->classname = $event['class'];
                $eventobj->displayname = $event['name'];
                $eventobj->checked = isset($this->selectedevents[$event['class']]);
                $events[] = $eventobj;
            }
            $category->events = $events;
            $categories[] = $category;
        }

        $data->categories = $categories;
        $data->hascategories = !empty($categories);

        return $data;
    }

    /**
     * Format a component name for display.
     *
     * @param string $component Component name (e.g., 'core', 'mod_assign')
     * @return string Formatted name (e.g., 'Core', 'Mod Assign')
     */
    private function format_category_name(string $component): string {
        if ($component === 'core') {
            return get_string('category_core_simple', 'local_mc_plugin');
        }
        return ucwords(str_replace('_', ' ', $component));
    }

    /**
     * Get JavaScript configuration for AMD module initialization.
     *
     * @return array Configuration array for js_call_amd
     */
    public function get_js_config(): array {
        return [
            'inputId' => $this->inputid,
        ];
    }
}
