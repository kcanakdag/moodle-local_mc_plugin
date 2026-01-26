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
 * Analytics renderer - prepares template context for analytics dashboard.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local;

/**
 * Helper class to prepare analytics template context.
 */
class analytics_renderer {
    /** @var array Color palette for event chart */
    private const EVENT_COLORS = ['#fd7e14', '#ffc107', '#20c997', '#6f42c1', '#e83e8c', '#17a2b8'];

    /** @var array Color palette for course chart */
    private const COURSE_COLORS = ['#0d6efd', '#6610f2', '#6f42c1', '#0dcaf0', '#198754'];

    /** @var array Color palette for user avatars */
    private const USER_COLORS = ['#fd7e14', '#20c997', '#6f42c1', '#0d6efd', '#e83e8c'];

    /**
     * Prepare template context from raw analytics data.
     *
     * @param array $summary Summary stats
     * @param array $events Events distribution
     * @param array $courses Top courses
     * @param array $users Top users
     * @param array $timeline Activity timeline
     * @return array Template context
     */
    public static function prepare_template_context($summary, $events, $courses, $users, $timeline) {
        $eventsdata = self::format_events($events);
        $coursesdata = self::format_courses($courses);
        $usersdata = self::format_users($users);
        $timelinedata = self::format_timeline($timeline);

        return [
            'totalevents' => number_format($summary['total_events']),
            'uniqueusers' => number_format($summary['unique_users']),
            'activecourses' => number_format($summary['active_courses']),
            'events' => $eventsdata,
            'hasevents' => !empty($eventsdata),
            'courses' => $coursesdata,
            'hascourses' => !empty($coursesdata),
            'users' => $usersdata,
            'hasusers' => !empty($usersdata),
            'timeline' => $timelinedata,
            'hastimeline' => !empty($timelinedata),
            'timelinestart' => !empty($timeline) ? $timeline[0]['label'] : '',
            'timelineend' => !empty($timeline) ? $timeline[count($timeline) - 1]['label'] : '',
        ];
    }

    /**
     * Format events for donut chart display.
     *
     * @param array $events Raw events data
     * @return array Formatted events for template
     */
    private static function format_events(array $events): array {
        $total = array_sum(array_column($events, 'count'));
        $data = [];
        $offset = 0;

        foreach (array_slice($events, 0, 6) as $index => $event) {
            $percent = $total > 0 ? round(($event['count'] / $total) * 100) : 0;
            $data[] = [
                'label' => $event['label'],
                'count' => number_format($event['count']),
                'percent' => $percent,
                'color' => self::EVENT_COLORS[$index % count(self::EVENT_COLORS)],
                'dasharray' => $percent . ' ' . (100 - $percent),
                'dashoffset' => -$offset,
            ];
            $offset += $percent;
        }

        return $data;
    }

    /**
     * Format courses for progress bar display.
     *
     * @param array $courses Raw courses data
     * @return array Formatted courses for template
     */
    private static function format_courses(array $courses): array {
        $maxcount = !empty($courses) ? (int)$courses[0]->eventcount : 1;
        $data = [];

        foreach ($courses as $index => $course) {
            $name = $course->shortname ?: $course->fullname;
            $data[] = [
                'name' => strlen($name) > 25 ? substr($name, 0, 23) . '...' : $name,
                'count' => number_format((int)$course->eventcount),
                'percent' => round(((int)$course->eventcount / $maxcount) * 100),
                'color' => self::COURSE_COLORS[$index % count(self::COURSE_COLORS)],
            ];
        }

        return $data;
    }

    /**
     * Format users with initials for avatar display.
     *
     * @param array $users Raw users data
     * @return array Formatted users for template
     */
    private static function format_users(array $users): array {
        $data = [];

        foreach ($users as $index => $user) {
            $data[] = [
                'name' => $user['fullname'],
                'initials' => self::get_initials($user['fullname']),
                'count' => number_format($user['count']),
                'bgcolor' => self::USER_COLORS[$index % count(self::USER_COLORS)],
            ];
        }

        return $data;
    }

    /**
     * Get initials from a full name.
     *
     * @param string $fullname The full name
     * @return string Up to 2 character initials
     */
    private static function get_initials(string $fullname): string {
        $names = explode(' ', $fullname);
        $initials = '';
        foreach (array_slice($names, 0, 2) as $name) {
            $initials .= strtoupper(substr($name, 0, 1));
        }
        return $initials ?: '?';
    }

    /**
     * Format timeline for bar chart display.
     *
     * @param array $timeline Raw timeline data
     * @return array Formatted timeline for template
     */
    private static function format_timeline(array $timeline): array {
        $maxcount = !empty($timeline) ? max(array_column($timeline, 'count')) : 1;
        $maxcount = max($maxcount, 1); // Avoid division by zero.
        $data = [];

        foreach ($timeline as $day) {
            $data[] = [
                'label' => $day['label'],
                'count' => number_format($day['count']),
                'height' => max(4, round(($day['count'] / $maxcount) * 100)),
            ];
        }

        return $data;
    }
}
