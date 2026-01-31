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
 * External function to get analytics data.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_mc_plugin\local\analytics_service;
use local_mc_plugin\local\analytics_renderer;

/**
 * External function to get analytics data via AJAX.
 */
class get_analytics extends external_api {
    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'days' => new external_value(PARAM_INT, 'Number of days', VALUE_DEFAULT, 30),
            'category' => new external_value(PARAM_ALPHA, 'Event category', VALUE_DEFAULT, 'all'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $days Number of days
     * @param string $category Event category
     * @return array Analytics data with rendered HTML
     */
    public static function execute(int $days = 30, string $category = 'all'): array {
        global $OUTPUT;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'days' => $days,
            'category' => $category,
        ]);

        // Check capability.
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        // Validate inputs.
        if (!in_array($params['days'], [7, 30, 90])) {
            $params['days'] = 30;
        }
        $validcats = array_keys(analytics_service::EVENT_CATEGORIES);
        if (!in_array($params['category'], $validcats)) {
            $params['category'] = 'all';
        }

        $service = new analytics_service();

        // Fetch data.
        $summary = $service->get_summary($params['days'], $params['category']);
        $courses = $service->get_events_by_course($params['days'], 5, $params['category']);
        $events = $service->get_event_distribution($params['days'], $params['category']);
        $timeline = $service->get_activity_timeline($params['days'], $params['category']);
        $users = $service->get_top_users($params['days'], 5, $params['category']);

        // Use the renderer to prepare template context.
        $templatecontext = analytics_renderer::prepare_template_context(
            $summary,
            $events,
            $courses,
            $users,
            $timeline
        );

        // Render the template.
        $html = $OUTPUT->render_from_template('local_mc_plugin/analytics_content', $templatecontext);

        return [
            'html' => $html,
            'days' => $params['days'],
            'category' => $params['category'],
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Rendered HTML content'),
            'days' => new external_value(PARAM_INT, 'Days'),
            'category' => new external_value(PARAM_ALPHA, 'Category'),
        ]);
    }
}
