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
 * Course event observer for automatic course sync to MoodleConnect.
 *
 * This observer automatically syncs course changes to MoodleConnect when:
 * - A course is created
 * - A course is updated
 * - A course is deleted
 *
 * This runs independently of user-selected monitored events.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin;

use local_mc_plugin\local\moodleconnect_client;

/**
 * Course event observer class for syncing course changes to MoodleConnect.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_observer {
    /**
     * Handle course created event.
     *
     * @param \core\event\course_created $event The course created event
     * @return void
     */
    public static function course_created(\core\event\course_created $event) {
        // Only sync if connected to MoodleConnect.
        if (!self::is_connected()) {
            return;
        }

        $courseid = $event->objectid;
        $course = get_course($courseid);

        if ($course) {
            $coursedata = self::extract_course_data($course);
            moodleconnect_client::sync_courses([$coursedata], 'incremental');
        }
    }

    /**
     * Handle course updated event.
     *
     * @param \core\event\course_updated $event The course updated event
     * @return void
     */
    public static function course_updated(\core\event\course_updated $event) {
        // Only sync if connected to MoodleConnect.
        if (!self::is_connected()) {
            return;
        }

        $courseid = $event->objectid;
        $course = get_course($courseid);

        if ($course) {
            $coursedata = self::extract_course_data($course);
            moodleconnect_client::sync_courses([$coursedata], 'incremental');
        }
    }

    /**
     * Handle course deleted event.
     *
     * @param \core\event\course_deleted $event The course deleted event
     * @return void
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        // Only sync if connected to MoodleConnect.
        if (!self::is_connected()) {
            return;
        }

        $courseid = $event->objectid;
        moodleconnect_client::delete_course($courseid);
    }

    /**
     * Check if the plugin is connected to MoodleConnect.
     *
     * @return bool True if connected (has site_key and site_secret)
     */
    private static function is_connected(): bool {
        $sitekey = get_config('local_mc_plugin', 'site_key');
        $sitesecret = get_config('local_mc_plugin', 'site_secret');
        return !empty($sitekey) && !empty($sitesecret);
    }

    /**
     * Extract course data for syncing.
     *
     * @param object $course The course object
     * @return array Course data array
     */
    private static function extract_course_data($course): array {
        global $DB;

        $categoryname = '';
        if (!empty($course->category)) {
            $category = $DB->get_record('course_categories', ['id' => $course->category]);
            if ($category) {
                $categoryname = $category->name;
            }
        }

        return [
            'id' => $course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname,
            'idnumber' => $course->idnumber ?? '',
            'category_id' => $course->category ?? null,
            'category_name' => $categoryname,
            'visible' => (bool) $course->visible,
        ];
    }
}
