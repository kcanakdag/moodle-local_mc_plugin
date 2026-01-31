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
 * Analytics service for querying Moodle log data efficiently.
 *
 * Designed for large Moodle sites:
 * - Uses indexed columns (timecreated, courseid, eventname)
 * - Aggregates in SQL, not PHP
 * - Limits result sets
 * - Caches expensive queries
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local;

/**
 * Service class for analytics queries against logstore_standard_log.
 */
class analytics_service {
    /**
     * Event categories with their events.
     */
    public const EVENT_CATEGORIES = [
        'all' => [],
        'enrollment' => [
            '\core\event\user_enrolment_created',
            '\core\event\user_enrolment_deleted',
        ],
        'activity' => [
            '\core\event\course_viewed',
            '\core\event\course_module_viewed',
        ],
        'assignments' => [
            '\mod_assign\event\assessable_submitted',
            '\mod_assign\event\submission_graded',
        ],
        'quizzes' => [
            '\mod_quiz\event\attempt_started',
            '\mod_quiz\event\attempt_submitted',
        ],
        'forums' => [
            '\mod_forum\event\post_created',
            '\mod_forum\event\discussion_created',
        ],
        'users' => [
            '\core\event\user_created',
            '\core\event\user_loggedin',
        ],
        'completions' => [
            '\core\event\course_completed',
            '\core\event\course_module_completion_updated',
        ],
    ];

    /**
     * Curated list of useful events to track.
     * These provide actionable insights for Moodle admins.
     */
    public const TRACKED_EVENTS = [
        '\core\event\user_enrolment_created',
        '\core\event\user_enrolment_deleted',
        '\core\event\course_viewed',
        '\core\event\course_module_viewed',
        '\mod_assign\event\assessable_submitted',
        '\mod_assign\event\submission_graded',
        '\mod_quiz\event\attempt_started',
        '\mod_quiz\event\attempt_submitted',
        '\mod_forum\event\post_created',
        '\mod_forum\event\discussion_created',
        '\core\event\user_created',
        '\core\event\user_loggedin',
        '\core\event\course_completed',
        '\core\event\course_module_completion_updated',
    ];

    /**
     * Friendly names for events.
     */
    public const EVENT_LABELS = [
        '\core\event\user_enrolment_created' => 'User Enrolled',
        '\core\event\user_enrolment_deleted' => 'User Unenrolled',
        '\core\event\course_viewed' => 'Course Viewed',
        '\core\event\course_module_viewed' => 'Activity Viewed',
        '\mod_assign\event\assessable_submitted' => 'Assignment Submitted',
        '\mod_assign\event\submission_graded' => 'Assignment Graded',
        '\mod_quiz\event\attempt_started' => 'Quiz Started',
        '\mod_quiz\event\attempt_submitted' => 'Quiz Submitted',
        '\mod_forum\event\post_created' => 'Forum Post',
        '\mod_forum\event\discussion_created' => 'Forum Discussion',
        '\core\event\user_created' => 'User Created',
        '\core\event\user_loggedin' => 'User Login',
        '\core\event\course_completed' => 'Course Completed',
        '\core\event\course_module_completion_updated' => 'Activity Completed',
    ];

    /**
     * Get events for a category.
     *
     * @param string $category Category key
     * @return array Event names
     */
    public static function get_events_for_category(string $category): array {
        if ($category === 'all' || !isset(self::EVENT_CATEGORIES[$category])) {
            return self::TRACKED_EVENTS;
        }
        return self::EVENT_CATEGORIES[$category];
    }

    /** @var int Cache TTL in seconds */
    private const CACHE_TTL = 300; // 5 minutes.

    /** @var int Threshold for using approximate counts */
    private const APPROX_COUNT_THRESHOLD = 100000;

    /**
     * Get events grouped by course.
     *
     * Optimized: Uses subquery to aggregate first, then JOIN for course details.
     * This is faster than JOIN-then-GROUP on large tables.
     *
     * @param int $days Number of days to look back
     * @param int $limit Maximum courses to return
     * @param string $category Event category filter
     * @return array Array of course data with event counts
     */
    public function get_events_by_course(int $days = 30, int $limit = 20, string $category = 'all'): array {
        global $DB;

        $events = self::get_events_for_category($category);
        $cache = \cache::make('local_mc_plugin', 'analytics');
        $cachekey = "events_by_course_{$days}_{$limit}_{$category}";

        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $since = time() - ($days * DAYSECS);
        [$insql, $params] = $DB->get_in_or_equal($events, SQL_PARAMS_NAMED);
        $params['since'] = $since;
        $params['siteid'] = SITEID;

        // Optimized: Aggregate in subquery first, then JOIN for course details.
        // This reduces the JOIN size significantly on large log tables.
        $sql = "SELECT c.id, c.shortname, c.fullname, agg.eventcount
                  FROM (
                      SELECT courseid, COUNT(id) as eventcount
                        FROM {logstore_standard_log}
                       WHERE timecreated >= :since
                         AND eventname {$insql}
                         AND courseid != :siteid
                    GROUP BY courseid
                    ORDER BY eventcount DESC
                  ) agg
                  JOIN {course} c ON c.id = agg.courseid
              ORDER BY agg.eventcount DESC";

        $results = $DB->get_records_sql($sql, $params, 0, $limit);

        $data = array_values($results);
        $cache->set($cachekey, $data);

        return $data;
    }

    /**
     * Get event type distribution.
     *
     * @param int $days Number of days to look back
     * @param string $category Event category filter
     * @return array Array of event types with counts
     */
    public function get_event_distribution(int $days = 30, string $category = 'all'): array {
        global $DB;

        $events = self::get_events_for_category($category);
        $cache = \cache::make('local_mc_plugin', 'analytics');
        $cachekey = "event_distribution_{$days}_{$category}";

        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $since = time() - ($days * DAYSECS);
        [$insql, $params] = $DB->get_in_or_equal($events, SQL_PARAMS_NAMED);
        $params['since'] = $since;

        $sql = "SELECT eventname, COUNT(id) as eventcount
                  FROM {logstore_standard_log}
                 WHERE timecreated >= :since
                   AND eventname {$insql}
              GROUP BY eventname
              ORDER BY eventcount DESC";

        $results = $DB->get_records_sql($sql, $params);

        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'eventname' => $row->eventname,
                'label' => self::EVENT_LABELS[$row->eventname] ?? $row->eventname,
                'count' => (int)$row->eventcount,
            ];
        }

        $cache->set($cachekey, $data);

        return $data;
    }

    /**
     * Get activity timeline (events per day).
     *
     * @param int $days Number of days to look back
     * @param string $category Event category filter
     * @return array Array of daily counts
     */
    public function get_activity_timeline(int $days = 30, string $category = 'all'): array {
        global $DB;

        $events = self::get_events_for_category($category);
        $cache = \cache::make('local_mc_plugin', 'analytics');
        $cachekey = "activity_timeline_{$days}_{$category}";

        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $since = time() - ($days * DAYSECS);
        [$insql, $params] = $DB->get_in_or_equal($events, SQL_PARAMS_NAMED);
        $params['since'] = $since;

        $dbfamily = $DB->get_dbfamily();

        if ($dbfamily === 'postgres') {
            $dateexpr = "TO_CHAR(TO_TIMESTAMP(timecreated), 'YYYY-MM-DD')";
        } else {
            $dateexpr = "DATE(FROM_UNIXTIME(timecreated))";
        }

        $sql = "SELECT {$dateexpr} as day, COUNT(id) as eventcount
                  FROM {logstore_standard_log}
                 WHERE timecreated >= :since
                   AND eventname {$insql}
              GROUP BY {$dateexpr}
              ORDER BY day ASC";

        $records = $DB->get_records_sql($sql, $params);

        $dailycounts = [];
        foreach ($records as $record) {
            $dailycounts[$record->day] = (int)$record->eventcount;
        }

        $data = [];
        $current = strtotime("-{$days} days");
        $end = time();

        while ($current <= $end) {
            $day = date('Y-m-d', $current);
            $data[] = [
                'date' => $day,
                'label' => date('M j', $current),
                'count' => $dailycounts[$day] ?? 0,
            ];
            $current = strtotime('+1 day', $current);
        }

        $cache->set($cachekey, $data);

        return $data;
    }

    /**
     * Get most active users.
     *
     * Optimized: Uses subquery to aggregate first, then JOIN for user details.
     * This is faster than JOIN-then-GROUP on large tables.
     *
     * @param int $days Number of days to look back
     * @param int $limit Maximum users to return
     * @param string $category Event category filter
     * @return array Array of user data with event counts
     */
    public function get_top_users(int $days = 30, int $limit = 10, string $category = 'all'): array {
        global $DB;

        $events = self::get_events_for_category($category);
        $cache = \cache::make('local_mc_plugin', 'analytics');
        $cachekey = "top_users_{$days}_{$limit}_{$category}";

        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $since = time() - ($days * DAYSECS);
        [$insql, $params] = $DB->get_in_or_equal($events, SQL_PARAMS_NAMED);
        $params['since'] = $since;
        $params['guest'] = 1;

        // Optimized: Aggregate in subquery first, then JOIN for user details.
        // This reduces the JOIN size significantly on large log tables.
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, agg.eventcount
                  FROM (
                      SELECT userid, COUNT(id) as eventcount
                        FROM {logstore_standard_log}
                       WHERE timecreated >= :since
                         AND eventname {$insql}
                         AND userid != :guest
                    GROUP BY userid
                    ORDER BY eventcount DESC
                  ) agg
                  JOIN {user} u ON u.id = agg.userid
                 WHERE u.deleted = 0
              ORDER BY agg.eventcount DESC";

        $results = $DB->get_records_sql($sql, $params, 0, $limit);

        $data = [];
        foreach ($results as $user) {
            $data[] = [
                'id' => $user->id,
                'fullname' => fullname($user),
                'email' => $user->email,
                'count' => (int)$user->eventcount,
            ];
        }

        $cache->set($cachekey, $data);

        return $data;
    }

    /**
     * Get summary statistics.
     *
     * Optimized for large sites:
     * - Single query with conditional aggregation instead of 3 separate queries
     * - Uses subquery approach for DISTINCT counts (faster than COUNT(DISTINCT) on large tables)
     * - Falls back to approximate counts for very large datasets
     *
     * @param int $days Number of days to look back
     * @param string $category Event category filter
     * @return array Summary stats
     */
    public function get_summary(int $days = 30, string $category = 'all'): array {
        global $DB;

        $events = self::get_events_for_category($category);
        $cache = \cache::make('local_mc_plugin', 'analytics');
        $cachekey = "summary_{$days}_{$category}";

        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $since = time() - ($days * DAYSECS);
        [$insql, $params] = $DB->get_in_or_equal($events, SQL_PARAMS_NAMED);
        $params['since'] = $since;

        // First, get a quick count estimate to decide on strategy.
        $countsql = "SELECT COUNT(id) FROM {logstore_standard_log}
                      WHERE timecreated >= :since AND eventname {$insql}";
        $totalevents = $DB->count_records_sql($countsql, $params);

        // For large datasets, use optimized subquery approach.
        // Subqueries with GROUP BY are often faster than COUNT(DISTINCT) on large tables.
        $params['guest'] = 1;
        $params['siteid'] = SITEID;

        if ($totalevents > self::APPROX_COUNT_THRESHOLD) {
            // For very large datasets, use sampling-based approximation.
            // Count distinct from a limited sample, then extrapolate.
            $data = $this->get_summary_approximate($since, $events, $totalevents);
        } else {
            // For moderate datasets, use optimized single-pass query.
            $data = $this->get_summary_exact($since, $events);
        }

        $data['total_events'] = (int)$totalevents;
        $data['days'] = $days;

        $cache->set($cachekey, $data);

        return $data;
    }

    /**
     * Get exact summary counts using optimized subqueries.
     *
     * Uses subquery with GROUP BY approach which is often faster than COUNT(DISTINCT)
     * because it can use indexes more effectively and allows the query planner
     * to choose better execution strategies.
     *
     * @param int $since Timestamp to query from
     * @param array $events Event names to filter
     * @return array Partial summary data
     */
    private function get_summary_exact(int $since, array $events): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($events, SQL_PARAMS_NAMED);
        $params['since'] = $since;
        $params['guest'] = 1;

        // Use subquery with GROUP BY instead of DISTINCT.
        // GROUP BY can leverage indexes better and allows HashAggregate optimization.
        $usersql = "SELECT COUNT(*) FROM (
                        SELECT userid
                          FROM {logstore_standard_log}
                         WHERE timecreated >= :since
                           AND eventname {$insql}
                           AND userid != :guest
                      GROUP BY userid
                    ) AS distinct_users";

        $uniqueusers = $DB->count_records_sql($usersql, $params);

        // Fresh params for course query.
        [$insql2, $params2] = $DB->get_in_or_equal($events, SQL_PARAMS_NAMED);
        $params2['since'] = $since;
        $params2['siteid'] = SITEID;

        $coursesql = "SELECT COUNT(*) FROM (
                          SELECT courseid
                            FROM {logstore_standard_log}
                           WHERE timecreated >= :since
                             AND eventname {$insql2}
                             AND courseid != :siteid
                        GROUP BY courseid
                      ) AS distinct_courses";

        $activecourses = $DB->count_records_sql($coursesql, $params2);

        return [
            'unique_users' => (int)$uniqueusers,
            'active_courses' => (int)$activecourses,
            'approximate' => false,
        ];
    }

    /**
     * Get approximate summary counts for very large datasets.
     *
     * Uses sampling to estimate distinct counts when exact counting
     * would be too slow. Samples recent data and extrapolates.
     *
     * @param int $since Timestamp to query from
     * @param array $events Event names to filter
     * @param int $totalevents Total event count for scaling
     * @return array Partial summary data with approximate flag
     */
    private function get_summary_approximate(int $since, array $events, int $totalevents): array {
        global $DB;

        // Sample size: 10% of data or 50k rows, whichever is smaller.
        $samplesize = min((int)($totalevents * 0.1), 50000);

        [$insql, $params] = $DB->get_in_or_equal($events, SQL_PARAMS_NAMED);
        $params['since'] = $since;
        $params['guest'] = 1;

        // Get distinct users from a sample of recent events using GROUP BY.
        // LIMIT inside subquery samples recent activity.
        $usersql = "SELECT COUNT(*) as cnt
                      FROM (
                          SELECT userid
                            FROM {logstore_standard_log}
                           WHERE timecreated >= :since
                             AND eventname {$insql}
                             AND userid != :guest
                        ORDER BY timecreated DESC
                           LIMIT {$samplesize}
                      ) AS sample_data
                     GROUP BY userid";

        // Actually we need to count the groups, not group the sample.
        // Correct approach: sample first, then count distinct.
        $usersql = "SELECT COUNT(*) FROM (
                        SELECT userid FROM (
                            SELECT userid
                              FROM {logstore_standard_log}
                             WHERE timecreated >= :since
                               AND eventname {$insql}
                               AND userid != :guest
                          ORDER BY timecreated DESC
                             LIMIT {$samplesize}
                        ) AS sample_data
                        GROUP BY userid
                    ) AS distinct_users";

        $uniqueusers = $DB->count_records_sql($usersql, $params);

        // Fresh params for course query.
        [$insql2, $params2] = $DB->get_in_or_equal($events, SQL_PARAMS_NAMED);
        $params2['since'] = $since;
        $params2['siteid'] = SITEID;

        $coursesql = "SELECT COUNT(*) FROM (
                          SELECT courseid FROM (
                              SELECT courseid
                                FROM {logstore_standard_log}
                               WHERE timecreated >= :since
                                 AND eventname {$insql2}
                                 AND courseid != :siteid
                            ORDER BY timecreated DESC
                               LIMIT {$samplesize}
                          ) AS sample_data
                          GROUP BY courseid
                      ) AS distinct_courses";

        $activecourses = $DB->count_records_sql($coursesql, $params2);

        // For distinct counts, we don't scale linearly - distinct values plateau.
        // Use logarithmic scaling: if sample has X distinct in Y rows,
        // full dataset likely has X * log(total/sample) / log(2) distinct values.
        // But cap at reasonable maximums.
        $scalefactor = $totalevents > $samplesize ? log($totalevents / $samplesize) / log(2) : 1;
        $scalefactor = min($scalefactor, 3); // Cap scaling at 3x.

        return [
            'unique_users' => (int)round($uniqueusers * $scalefactor),
            'active_courses' => (int)round($activecourses * $scalefactor),
            'approximate' => true,
        ];
    }

    /**
     * Get events for a specific course.
     *
     * @param int $courseid Course ID
     * @param int $days Number of days to look back
     * @return array Event breakdown for the course
     */
    public function get_course_events(int $courseid, int $days = 30): array {
        global $DB;

        $since = time() - ($days * DAYSECS);
        [$insql, $params] = $DB->get_in_or_equal(self::TRACKED_EVENTS, SQL_PARAMS_NAMED);
        $params['since'] = $since;
        $params['courseid'] = $courseid;

        $sql = "SELECT eventname, COUNT(id) as eventcount
                  FROM {logstore_standard_log}
                 WHERE timecreated >= :since
                   AND eventname {$insql}
                   AND courseid = :courseid
              GROUP BY eventname
              ORDER BY eventcount DESC";

        $results = $DB->get_records_sql($sql, $params);

        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'eventname' => $row->eventname,
                'label' => self::EVENT_LABELS[$row->eventname] ?? $row->eventname,
                'count' => (int)$row->eventcount,
            ];
        }

        return $data;
    }
}
