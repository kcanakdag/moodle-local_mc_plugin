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
    'local_mc_plugin/connect'
], function(EventSelector, ConnectionStatus, Connect) {
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
         * @param {string} cfg.syncUrl URL to sync_schema.php
         * @param {string} cfg.sesskey Moodle session key
         * @param {string} cfg.eventInputId Event selector input ID for counter refresh
         */
        initConnectionStatus: function(cfg) {
            ConnectionStatus.init(cfg);
        },

        /**
         * Initialize the connect button component.
         *
         * @param {Object} cfg Configuration object
         * @param {string} cfg.connectUrl URL to connect.php
         * @param {string} cfg.saveUrl URL to ajax_save.php
         * @param {string} cfg.apiUrl MoodleConnect API URL
         * @param {string} cfg.frontendUrl MoodleConnect frontend URL
         * @param {string} cfg.sesskey Moodle session key
         * @param {boolean} cfg.isConnected Whether already connected
         */
        initConnect: function(cfg) {
            Connect.init(cfg);
        }
    };
});
