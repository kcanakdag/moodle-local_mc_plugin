/**
 * Template rendering helper module for the admin settings page.
 *
 * Provides functions to render Mustache templates client-side using
 * Moodle's Templates API for dynamic UI updates.
 *
 * @module     local_mc_plugin/local/admin/templates
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/templates', 'core/notification'], function(Templates, Notification) {
    "use strict";

    return {
        /**
         * Render the connection status into a container.
         *
         * @param {HTMLElement} container The container element to render into
         * @param {Object} context The template context
         * @returns {Promise<void>}
         */
        renderConnectionStatus: async function(container, context) {
            try {
                const {html, js} = await Templates.renderForPromise(
                    'local_mc_plugin/connection_status_dynamic',
                    context
                );
                Templates.replaceNodeContents(container, html, js);
            } catch (error) {
                Notification.exception(error);
            }
        },

        /**
         * Render an action result message into a container.
         *
         * @param {HTMLElement} container The container element to render into
         * @param {Object} context The template context
         * @returns {Promise<void>}
         */
        renderActionResult: async function(container, context) {
            try {
                const {html, js} = await Templates.renderForPromise(
                    'local_mc_plugin/action_result',
                    context
                );
                Templates.replaceNodeContents(container, html, js);
            } catch (error) {
                Notification.exception(error);
            }
        },

        /**
         * Build context object for connection status template.
         *
         * @param {boolean} connected Whether connected
         * @param {string} statusText Status text to display
         * @param {string|null} siteName Site name if connected
         * @param {string|null} syncStatus Sync status text
         * @returns {Object} Template context object
         */
        buildConnectionStatusContext: function(connected, statusText, siteName, syncStatus) {
            siteName = siteName || null;
            syncStatus = syncStatus || null;
            return {
                connected: connected,
                statusclass: connected ? 'text-success' : 'text-danger',
                dotclass: connected ? 'text-success' : 'text-danger',
                statustext: statusText,
                sitename: siteName || '',
                hassitename: Boolean(siteName),
                syncstatus: syncStatus || '',
                hassyncstatus: Boolean(syncStatus),
            };
        },

        /**
         * Build context object for action result template.
         *
         * @param {boolean} success Whether the operation was successful
         * @param {string} message Message text to display
         * @returns {Object} Template context object
         */
        buildActionResultContext: function(success, message) {
            return {
                success: success,
                message: message,
                alertclass: success ? 'alert-success' : 'alert-danger',
            };
        }
    };
});
