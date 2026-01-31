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
 * Analytics dashboard for MoodleConnect plugin.
 *
 * Displays event analytics using Moodle's logstore data.
 * Admin-only access with efficient queries for large sites.
 *
 * Uses Moodle's native Charts API for rendering.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_mc_plugin\local\analytics_service;

require_login();
require_capability('moodle/site:config', context_system::instance());

// Get time range parameter.
$days = optional_param('days', 30, PARAM_INT);
$alloweddays = [7, 30, 90];
if (!in_array($days, $alloweddays)) {
    $days = 30;
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/mc_plugin/analytics.php', ['days' => $days]));
$PAGE->set_title(get_string('analytics_title', 'local_mc_plugin'));
$PAGE->set_heading(get_string('analytics_title', 'local_mc_plugin'));
$PAGE->set_pagelayout('admin');

// Add navigation.
$PAGE->navbar->add(get_string('pluginname', 'local_mc_plugin'));
$PAGE->navbar->add(get_string('analytics_title', 'local_mc_plugin'));

// Load analytics service.
$service = new analytics_service();

// Fetch data.
$summary = $service->get_summary($days);
$eventsbycourse = $service->get_events_by_course($days, 15);
$eventdistribution = $service->get_event_distribution($days);
$timeline = $service->get_activity_timeline($days);
$topusers = $service->get_top_users($days, 10);

// Build Moodle charts using the Charts API.

// Timeline chart (line chart).
$timelinechart = null;
if (!empty($timeline)) {
    $timelinechart = new \core\chart_line();
    $timelinechart->set_title(get_string('analytics_activity_timeline', 'local_mc_plugin'));

    $timelinelabels = [];
    $timelinevalues = [];
    foreach ($timeline as $day) {
        $timelinelabels[] = $day['label'];
        $timelinevalues[] = $day['count'];
    }

    $series = new \core\chart_series(get_string('analytics_events', 'local_mc_plugin'), $timelinevalues);
    $series->set_smooth(true);
    $timelinechart->add_series($series);
    $timelinechart->set_labels($timelinelabels);
}

// Course chart (horizontal bar chart).
$coursechart = null;
if (!empty($eventsbycourse)) {
    $coursechart = new \core\chart_bar();
    $coursechart->set_title(get_string('analytics_events_by_course', 'local_mc_plugin'));
    $coursechart->set_horizontal(true);

    $courselabels = [];
    $coursevalues = [];
    foreach ($eventsbycourse as $course) {
        $courselabels[] = $course->shortname ?: $course->fullname;
        $coursevalues[] = (int)$course->eventcount;
    }

    $series = new \core\chart_series(get_string('analytics_events', 'local_mc_plugin'), $coursevalues);
    $coursechart->add_series($series);
    $coursechart->set_labels($courselabels);
}

// Event distribution chart (pie/doughnut chart).
$eventchart = null;
if (!empty($eventdistribution)) {
    $eventchart = new \core\chart_pie();
    $eventchart->set_title(get_string('analytics_event_types', 'local_mc_plugin'));
    $eventchart->set_doughnut(true);

    $eventlabels = [];
    $eventvalues = [];
    foreach ($eventdistribution as $event) {
        $eventlabels[] = $event['label'];
        $eventvalues[] = $event['count'];
    }

    $series = new \core\chart_series(get_string('analytics_events', 'local_mc_plugin'), $eventvalues);
    $eventchart->add_series($series);
    $eventchart->set_labels($eventlabels);
}

// Build template context.
$templatecontext = [
    'days' => $days,
    'days_options' => [
        ['value' => 7, 'label' => get_string('analytics_7days', 'local_mc_plugin'), 'selected' => $days === 7],
        ['value' => 30, 'label' => get_string('analytics_30days', 'local_mc_plugin'), 'selected' => $days === 30],
        ['value' => 90, 'label' => get_string('analytics_90days', 'local_mc_plugin'), 'selected' => $days === 90],
    ],
    'base_url' => (new moodle_url('/local/mc_plugin/analytics.php'))->out(false),
    'summary' => [
        'total_events' => number_format($summary['total_events']),
        'unique_users' => number_format($summary['unique_users']),
        'active_courses' => number_format($summary['active_courses']),
    ],
    'has_course_data' => !empty($eventsbycourse),
    'courses' => array_map(function ($course) {
        return [
            'id' => $course->id,
            'name' => $course->fullname,
            'shortname' => $course->shortname,
            'count' => number_format($course->eventcount),
        ];
    }, $eventsbycourse),
    'has_user_data' => !empty($topusers),
    'users' => array_map(function ($user, $index) {
        return [
            'rank' => $index + 1,
            'fullname' => $user['fullname'],
            'email' => $user['email'],
            'count' => number_format($user['count']),
        ];
    }, $topusers, array_keys($topusers)),
    'timeline_chart' => $timelinechart ? $OUTPUT->render($timelinechart) : '',
    'course_chart' => $coursechart ? $OUTPUT->render($coursechart) : '',
    'event_chart' => $eventchart ? $OUTPUT->render($eventchart) : '',
    'settings_url' => (new moodle_url('/admin/settings.php', ['section' => 'local_mc_plugin']))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_mc_plugin/analytics_dashboard', $templatecontext);
echo $OUTPUT->footer();
