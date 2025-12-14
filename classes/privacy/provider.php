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
 * Privacy Subsystem for local_mc_plugin implementing null_provider.
 *
 * This plugin does not store any personal data locally in the Moodle database.
 * However, it transmits event data (which may contain personal information) to
 * an external service (MoodleConnect API).
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
        // This plugin does not store any data locally in Moodle tables.
        // However, it transmits event data to an external service.

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

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * Since this plugin does not store any data locally, this returns an empty contextlist.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        // This plugin does not store any personal data locally in Moodle.
        // All data is transmitted to an external service and not retained.
        $contextlist = new contextlist();
        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * Since this plugin does not store any data locally, there is nothing to export.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // This plugin does not store any personal data locally in Moodle.
        // Data is only transmitted to the external MoodleConnect service.
        // Users should contact the MoodleConnect service directly for data export.

        // Write a note explaining this to the user.
        $context = \context_system::instance();
        $subcontext = [get_string('pluginname', 'local_mc_plugin')];

        writer::with_context($context)->export_data(
            $subcontext,
            (object)[
                'note' => get_string('privacy:export:note', 'local_mc_plugin'),
            ]
        );
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * Since this plugin does not store any data locally, there is nothing to delete.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // This plugin does not store any personal data locally in Moodle.
        // No action required.
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * Since this plugin does not store any data locally, there is nothing to delete.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // This plugin does not store any personal data locally in Moodle.
        // No action required.
    }

    /**
     * Get the list of users who have data within a context.
     *
     * Since this plugin does not store any data locally, this returns an empty userlist.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        // This plugin does not store any personal data locally in Moodle.
        // No users to add to the list.
    }

    /**
     * Delete multiple users within a single context.
     *
     * Since this plugin does not store any data locally, there is nothing to delete.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        // This plugin does not store any personal data locally in Moodle.
        // No action required.
    }
}
