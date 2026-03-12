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
 * Privacy Subsystem implementation for local_mc_plugin.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\privacy;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for local_mc_plugin.
 *
 * This plugin stores local action execution records (local_mc_plugin_executions)
 * which contain user IDs. It also transmits event data to the external
 * MoodleConnect API.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    core_userlist_provider,
    metadata_provider,
    plugin_provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        // External data transmission.
        $collection->add_external_location_link(
            'moodleconnect_api',
            [
                'userid' => 'privacy:metadata:moodleconnect_api:userid',
                'username' => 'privacy:metadata:moodleconnect_api:username',
                'email' => 'privacy:metadata:moodleconnect_api:email',
                'firstname' => 'privacy:metadata:moodleconnect_api:firstname',
                'lastname' => 'privacy:metadata:moodleconnect_api:lastname',
                'idnumber' => 'privacy:metadata:moodleconnect_api:idnumber',
                'courseid' => 'privacy:metadata:moodleconnect_api:courseid',
                'coursename' => 'privacy:metadata:moodleconnect_api:coursename',
                'eventtype' => 'privacy:metadata:moodleconnect_api:eventtype',
                'eventdata' => 'privacy:metadata:moodleconnect_api:eventdata',
                'timecreated' => 'privacy:metadata:moodleconnect_api:timecreated',
            ],
            'privacy:metadata:moodleconnect_api'
        );

        // Local action execution records.
        $collection->add_database_table(
            'local_mc_plugin_executions',
            [
                'user_id' => 'privacy:metadata:executions:user_id',
                'action_type' => 'privacy:metadata:executions:action_type',
                'result' => 'privacy:metadata:executions:result',
                'executed_at' => 'privacy:metadata:executions:executed_at',
            ],
            'privacy:metadata:executions'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Execution records are stored at system context level.
        $sql = "SELECT ctx.id
                  FROM {local_mc_plugin_executions} e
                  JOIN {context} ctx ON ctx.contextlevel = :contextlevel AND ctx.instanceid = 0
                 WHERE e.user_id = :userid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_SYSTEM,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        // Only export if system context is in the approved list.
        $hassystemcontext = false;
        foreach ($contextlist->get_contexts() as $ctx) {
            if ($ctx->contextlevel == CONTEXT_SYSTEM) {
                $hassystemcontext = true;
                break;
            }
        }
        if (!$hassystemcontext) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();
        $subcontext = [get_string('pluginname', 'local_mc_plugin')];

        // Export execution records.
        $records = $DB->get_records('local_mc_plugin_executions', ['user_id' => $userid], 'executed_at ASC');
        if ($records) {
            $exportdata = [];
            foreach ($records as $record) {
                $exportdata[] = (object) [
                    'action_type' => $record->action_type,
                    'result' => $record->result,
                    'executed_at' => \core_privacy\local\request\transform::datetime($record->executed_at),
                ];
            }
            writer::with_context($context)->export_data(
                array_merge($subcontext, ['executions']),
                (object) ['executions' => $exportdata]
            );
        }

        // Note about external data.
        writer::with_context($context)->export_data(
            $subcontext,
            (object) [
                'note' => get_string('privacy:export:note', 'local_mc_plugin'),
            ]
        );
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->delete_records('local_mc_plugin_executions');
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $DB->delete_records('local_mc_plugin_executions', ['user_id' => $userid]);
            }
        }
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $sql = "SELECT DISTINCT user_id AS userid
                      FROM {local_mc_plugin_executions}
                     WHERE user_id IS NOT NULL";
            $userlist->add_from_sql('userid', $sql, []);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_mc_plugin_executions', "user_id {$insql}", $inparams);
    }
}
