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
 * External function to fire user_updated events in batches for bulk sync.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\external;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * External function to fire user_updated events in batches for bulk sync.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_sync_fire extends external_api {
    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'offset' => new external_value(PARAM_INT, 'Start offset for user query'),
            'batch_size' => new external_value(PARAM_INT, 'Number of users per batch', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * Fire user_updated events for a batch of users.
     *
     * @param int $offset Start offset
     * @param int $batchsize Batch size (clamped 1-50)
     * @return array Result with processed count, skipped IDs, has_more flag
     */
    public static function execute(int $offset = 0, int $batchsize = 25): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'offset' => $offset,
            'batch_size' => $batchsize,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $sitekey = get_config('local_mc_plugin', 'site_key');
        if (empty($sitekey)) {
            return [
                'success' => false,
                'processed' => 0,
                'skipped' => [],
                'has_more' => false,
            ];
        }

        $offset = max(0, $params['offset']);
        $batchsize = max(1, min($params['batch_size'], 50));

        set_time_limit(120);

        $users = $DB->get_records_select(
            'user',
            'deleted = 0 AND suspended = 0 AND id > 1',
            null,
            'id ASC',
            '*',
            $offset,
            $batchsize
        );

        // Preload user contexts to avoid N+1 queries.
        $userids = array_keys($users);
        if (!empty($userids)) {
            [$insql, $ctxparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
            $ctxparams['ctxlevel'] = CONTEXT_USER;
            $ctxrecords = $DB->get_records_select(
                'context',
                "contextlevel = :ctxlevel AND instanceid $insql",
                $ctxparams
            );
            foreach ($ctxrecords as $ctxrecord) {
                \context::instance_by_id($ctxrecord->id, IGNORE_MISSING);
            }
        }

        $processed = 0;
        $skipped = [];
        foreach ($users as $user) {
            try {
                $event = \core\event\user_updated::create([
                    'objectid' => $user->id,
                    'relateduserid' => $user->id,
                    'context' => \context_user::instance($user->id),
                ]);
                $event->add_record_snapshot('user', $user);
                $event->trigger();
                $processed++;
            } catch (\Exception $e) {
                $skipped[] = (int)$user->id;
                continue;
            }
        }

        return [
            'success' => true,
            'processed' => $processed,
            'skipped' => $skipped,
            'has_more' => count($users) >= $batchsize,
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the batch succeeded'),
            'processed' => new external_value(PARAM_INT, 'Number of events fired'),
            'skipped' => new external_multiple_structure(
                new external_value(PARAM_INT, 'User ID'),
                'IDs of users that failed',
                VALUE_OPTIONAL
            ),
            'has_more' => new external_value(PARAM_BOOL, 'Whether more users remain'),
        ]);
    }
}
