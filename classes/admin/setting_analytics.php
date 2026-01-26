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
 * Admin setting for displaying analytics dashboard inline.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\admin;

use local_mc_plugin\local\analytics_service;
use local_mc_plugin\local\analytics_renderer;

/**
 * Custom admin setting that displays analytics inline in settings page.
 */
class setting_analytics extends \admin_setting {
    /**
     * Constructor.
     *
     * @param string $name Unique setting name
     */
    public function __construct($name) {
        $this->nosave = true;
        parent::__construct($name, '', '', '');
    }

    /**
     * Always returns true.
     *
     * @return bool
     */
    public function get_setting() {
        return true;
    }

    /**
     * Never writes anything.
     *
     * @param mixed $data Unused
     * @return string Empty string
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * Render the analytics.
     *
     * @param mixed $data Unused
     * @param string $query Unused
     * @return string HTML output
     */
    public function output_html($data, $query = '') {
        global $PAGE, $OUTPUT;

        $days = 30;
        $category = 'all';

        // Get initial data.
        $service = new analytics_service();
        $summary = $service->get_summary($days, $category);
        $courses = $service->get_events_by_course($days, 5, $category);
        $events = $service->get_event_distribution($days, $category);
        $timeline = $service->get_activity_timeline($days, $category);
        $users = $service->get_top_users($days, 5, $category);

        // Build categories for dropdown.
        $categories = [];
        foreach (array_keys(analytics_service::EVENT_CATEGORIES) as $cat) {
            $categories[] = [
                'value' => $cat,
                'label' => get_string('analytics_filter_' . $cat, 'local_mc_plugin'),
                'selected' => ($cat === $category),
            ];
        }

        // Prepare template context using the renderer.
        $templatecontext = analytics_renderer::prepare_template_context(
            $summary,
            $events,
            $courses,
            $users,
            $timeline
        );

        // Build main HTML.
        $html = '<div class="mc-analytics-dashboard" id="mc-analytics-container">';

        // Header row with title and filters.
        $html .= '<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">';
        $html .= '<h5 class="mb-0 text-dark">' . get_string('analytics_title', 'local_mc_plugin') . '</h5>';

        // Control bar - unified filters.
        $html .= '<div class="d-flex align-items-center">';

        // Category dropdown.
        $html .= '<select class="form-select form-select-sm mr-3" style="width:auto;height:38px;" data-category-select>';
        foreach ($categories as $cat) {
            $selected = $cat['selected'] ? ' selected' : '';
            $html .= '<option value="' . $cat['value'] . '"' . $selected . '>' . $cat['label'] . '</option>';
        }
        $html .= '</select>';

        // Time range buttons - matched height.
        $html .= '<div class="btn-group" role="group">';
        foreach ([7, 30, 90] as $d) {
            $active = ($d === $days) ? ' active' : '';
            $html .= '<button type="button" class="btn btn-outline-primary' . $active . '" ';
            $html .= 'data-days="' . $d . '" style="height:38px;padding:0 1rem;">';
            $html .= get_string("analytics_{$d}days", 'local_mc_plugin') . '</button>';
        }
        $html .= '</div>';

        $html .= '</div></div>';

        // Content area - rendered via Mustache template.
        $html .= '<div class="mc-analytics-content">';
        $html .= $OUTPUT->render_from_template('local_mc_plugin/analytics_content', $templatecontext);
        $html .= '</div>';

        $html .= '</div>';

        // Initialize JS.
        $PAGE->requires->js_call_amd('local_mc_plugin/analytics', 'init', [[
            'days' => $days,
            'category' => $category,
        ]]);

        return format_admin_setting($this, '', $html, '', false, '', null, $query);
    }
}
