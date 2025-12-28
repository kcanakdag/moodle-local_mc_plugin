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
 * Custom admin setting that displays synced events (read-only).
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Custom admin setting that displays synced events in a read-only format.
 *
 * Shows the events that MoodleConnect has configured this plugin to monitor,
 * including any course filters. This is useful for debugging and verification.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_synced_events extends \admin_setting {
    /**
     * Constructor.
     *
     * @param string $name Unique setting name
     */
    public function __construct($name) {
        parent::__construct(
            $name,
            get_string('synced_events_label', 'local_mc_plugin'),
            '',
            ''
        );
    }

    /**
     * Returns current value of this setting.
     *
     * @return bool Always returns true (this is a display, not a stored value)
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
        // Get the monitored events config.
        $eventsconfigjson = get_config('local_mc_plugin', 'monitored_events_config');
        $monitoredeventsstr = get_config('local_mc_plugin', 'monitored_events');

        $html = '<div class="local_mc_plugin_synced_events">';

        if (empty($monitoredeventsstr) && empty($eventsconfigjson)) {
            $html .= '<div class="alert alert-info">';
            $html .= get_string('synced_events_none', 'local_mc_plugin');
            $html .= '</div>';
        } else {
            // Parse the events config.
            $eventsconfig = [];
            if (!empty($eventsconfigjson)) {
                $eventsconfig = json_decode($eventsconfigjson, true) ?: [];
            }

            // If we have the new format with course filters, use it.
            if (!empty($eventsconfig)) {
                $html .= $this->render_events_with_filters($eventsconfig);
            } else {
                // Fall back to simple event list.
                $events = array_filter(array_map('trim', explode(',', $monitoredeventsstr)));
                $html .= $this->render_simple_events($events);
            }
        }

        $html .= '</div>';

        return format_admin_setting($this, $this->visiblename, $html, '', false, '', null, $query);
    }

    /**
     * Render events with course filter information.
     *
     * @param array $eventsconfig Array of event configs with course_filter
     * @return string HTML
     */
    private function render_events_with_filters(array $eventsconfig): string {
        if (empty($eventsconfig)) {
            return '<p class="text-muted">' . get_string('synced_events_none', 'local_mc_plugin') . '</p>';
        }

        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-sm table-striped">';
        $html .= '<thead class="thead-light">';
        $html .= '<tr>';
        $html .= '<th>' . get_string('synced_events_col_event', 'local_mc_plugin') . '</th>';
        $html .= '<th>' . get_string('synced_events_col_filter', 'local_mc_plugin') . '</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ($eventsconfig as $eventconfig) {
            $eventtype = $eventconfig['event_type'] ?? '';
            $coursefilter = $eventconfig['course_filter'] ?? null;

            // Format event name nicely.
            $eventdisplay = $this->format_event_name($eventtype);

            // Format course filter.
            $filterdisplay = $this->format_course_filter($coursefilter);

            $html .= '<tr>';
            $html .= '<td><code class="small">' . s($eventdisplay) . '</code></td>';
            $html .= '<td>' . $filterdisplay . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        $html .= '<p class="text-muted small mt-2">';
        $html .= get_string('synced_events_count', 'local_mc_plugin', count($eventsconfig));
        $html .= '</p>';

        return $html;
    }

    /**
     * Render simple event list (legacy format).
     *
     * @param array $events Array of event type strings
     * @return string HTML
     */
    private function render_simple_events(array $events): string {
        if (empty($events)) {
            return '<p class="text-muted">' . get_string('synced_events_none', 'local_mc_plugin') . '</p>';
        }

        $html = '<ul class="list-unstyled">';
        foreach ($events as $event) {
            $eventdisplay = $this->format_event_name($event);
            $html .= '<li><code class="small">' . s($eventdisplay) . '</code></li>';
        }
        $html .= '</ul>';

        $html .= '<p class="text-muted small">';
        $html .= get_string('synced_events_count', 'local_mc_plugin', count($events));
        $html .= '</p>';

        return $html;
    }

    /**
     * Format an event name for display.
     *
     * @param string $eventtype Full event class name
     * @return string Formatted name
     */
    private function format_event_name(string $eventtype): string {
        // Remove leading backslash.
        $eventtype = ltrim($eventtype, '\\');

        // Could make this prettier, but keeping it simple for debugging.
        return $eventtype;
    }

    /**
     * Format course filter for display.
     *
     * @param array|null $coursefilter Course filter config
     * @return string HTML for filter display
     */
    private function format_course_filter(?array $coursefilter): string {
        global $DB;

        if ($coursefilter === null) {
            return '<span class="badge badge-secondary">' .
                   get_string('synced_events_filter_all', 'local_mc_plugin') .
                   '</span>';
        }

        $mode = $coursefilter['mode'] ?? '';
        $courseids = $coursefilter['course_ids'] ?? [];

        if (empty($courseids)) {
            return '<span class="badge badge-secondary">' .
                   get_string('synced_events_filter_all', 'local_mc_plugin') .
                   '</span>';
        }

        $badgeclass = ($mode === 'include') ? 'badge-success' : 'badge-warning';
        $modestr = ($mode === 'include')
            ? get_string('synced_events_filter_include', 'local_mc_plugin')
            : get_string('synced_events_filter_exclude', 'local_mc_plugin');

        // Build course names list.
        $coursenames = [];
        foreach (array_slice($courseids, 0, 5) as $courseid) {
            $coursenames[] = $this->get_course_display_name($courseid);
        }

        $coursesstr = implode(', ', $coursenames);
        if (count($courseids) > 5) {
            $coursesstr .= ' +' . (count($courseids) - 5) . ' ' .
                           get_string('synced_events_filter_more', 'local_mc_plugin');
        }

        return '<span class="badge ' . $badgeclass . '">' . $modestr . '</span> ' .
               '<span class="text-muted small">(' . s($coursesstr) . ')</span>';
    }

    /**
     * Get display name for a course ID.
     *
     * @param int $courseid Course ID
     * @return string Course display name
     */
    private function get_course_display_name(int $courseid): string {
        global $DB, $SITE;

        // Course ID 1 is the site-level course (front page) in Moodle.
        if ($courseid == SITEID) {
            return get_string('synced_events_filter_sitewide', 'local_mc_plugin');
        }

        // Try to get the course name from database.
        try {
            $course = $DB->get_record('course', ['id' => $courseid], 'id, shortname, fullname');
            if ($course) {
                // Use shortname if available, otherwise fullname.
                return $course->shortname ?: $course->fullname;
            }
        } catch (\Exception $e) {
            // Database error - fall through to show course ID.
            debugging('Failed to get course name for ID ' . $courseid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Course not found - show ID with indicator.
        return get_string('synced_events_filter_unknown_course', 'local_mc_plugin', $courseid);
    }
}
