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
 * Repository module for AJAX calls to the backend.
 *
 * Uses Moodle's core/ajax to call External Services by methodname.
 *
 * @module     local_mc_plugin/local/admin/repository
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {
    "use strict";

    /**
     * Call a single external function and return its promise.
     *
     * @param {string} methodname The external function name
     * @param {Object} args The arguments
     * @returns {Promise<Object>} The response
     */
    const call = (methodname, args) => Ajax.call([{methodname, args}])[0];

    return {
        /**
         * Initialize the connection flow (get a token).
         *
         * @returns {Promise<Object>} Response with token or error
         */
        initConnection: function() {
            return call('local_mc_plugin_connect_init', {});
        },

        /**
         * Poll the MoodleConnect API for connection status.
         * This calls an external service (not Moodle), so it uses fetch directly.
         *
         * @param {string} apiUrl MoodleConnect API URL
         * @param {string} token Connection token
         * @returns {Promise<Object>} Status response
         */
        pollConnectionStatus: async function(apiUrl, token) {
            const response = await fetch(`${apiUrl}/connect/status?token=${encodeURIComponent(token)}`);
            return response.json();
        },

        /**
         * Save credentials to Moodle settings.
         *
         * @param {string} siteKey The site key
         * @param {string} siteSecret The site secret
         * @returns {Promise<Object>} Response with success status
         */
        saveCredentials: function(siteKey, siteSecret) {
            return call('local_mc_plugin_save_settings', {
                site_key: siteKey, // eslint-disable-line camelcase
                site_secret: siteSecret, // eslint-disable-line camelcase
            });
        },

        /**
         * Get connection status from the backend.
         *
         * @returns {Promise<Object>} Status response
         */
        getConnectionStatus: function() {
            return call('local_mc_plugin_get_connection_status', {});
        },

        /**
         * Save settings via AJAX.
         *
         * @param {Object} values Form values to save
         * @returns {Promise<Object>} Response with success status
         */
        saveSettings: function(values) {
            const args = {};
            if (values.siteKey) {
                args.site_key = values.siteKey; // eslint-disable-line camelcase
            }
            if (values.siteSecret) {
                args.site_secret = values.siteSecret; // eslint-disable-line camelcase
            }
            if (values.monitoredEvents !== undefined) {
                args.monitored_events = values.monitoredEvents; // eslint-disable-line camelcase
            }
            if (values.debugMode !== undefined) {
                args.debug_mode = values.debugMode; // eslint-disable-line camelcase
            }
            return call('local_mc_plugin_save_settings', args);
        },

        /**
         * Sync events to MoodleConnect.
         *
         * @returns {Promise<Object>} Response with success status and event count
         */
        syncEvents: function() {
            return call('local_mc_plugin_sync_events', {});
        },

        /**
         * Sync ALL events to MoodleConnect (for initial connection or resync).
         *
         * @returns {Promise<Object>} Response with success status and event count
         */
        syncAllEvents: function() {
            return call('local_mc_plugin_sync_all_events', {});
        },

        /**
         * Count active users for bulk sync preflight.
         *
         * @returns {Promise<Object>} Response with count and monitored status
         */
        bulkSyncCount: function() {
            return call('local_mc_plugin_bulk_sync_count', {});
        },

        /**
         * Fire user_updated events for a batch of users.
         *
         * @param {number} offset Start offset
         * @param {number} batchSize Number of users per batch
         * @returns {Promise<Object>} Response with processed count and has_more flag
         */
        bulkSyncFire: function(offset, batchSize) {
            return call('local_mc_plugin_bulk_sync_fire', {
                offset: offset,
                batch_size: batchSize, // eslint-disable-line camelcase
            });
        }
    };
});
