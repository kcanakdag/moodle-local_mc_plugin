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
 * Main entry point for the admin settings page.
 *
 * This module coordinates all admin page functionality.
 *
 * @module     local_mc_plugin/admin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'local_mc_plugin/local/admin/event_selector',
    'local_mc_plugin/local/admin/connection_status',
    'local_mc_plugin/connect',
    'local_mc_plugin/local/admin/action_buttons',
    'local_mc_plugin/local/admin/bulk_sync'
], function(EventSelector, ConnectionStatus, Connect, ActionButtons, BulkSync) {
    "use strict";

    return {
        /**
         * Initialize the event selector component.
         *
         * @param {Object} cfg Configuration object
         * @param {string} cfg.inputId The hidden input element ID
         */
        initEventSelector: function(cfg) {
            EventSelector.init(cfg.inputId);
        },

        /**
         * Initialize the connection status component.
         *
         * @param {Object} cfg Configuration object
         * @param {string} cfg.eventInputId Event selector input ID for counter refresh
         */
        initConnectionStatus: function(cfg) {
            ConnectionStatus.init(cfg);
        },

        /**
         * Initialize the connect button component.
         *
         * @param {Object} cfg Configuration object
         * @param {string} cfg.apiUrl MoodleConnect API URL
         * @param {string} cfg.frontendUrl MoodleConnect frontend URL
         * @param {boolean} cfg.isConnected Whether already connected
         */
        initConnect: function(cfg) {
            Connect.init(cfg);
        },

        /**
         * Initialize the action buttons component.
         */
        initActionButtons: function() {
            ActionButtons.init();
        },

        /**
         * Initialize the bulk sync component.
         */
        initBulkSync: function() {
            BulkSync.init();
        }
    };
});
