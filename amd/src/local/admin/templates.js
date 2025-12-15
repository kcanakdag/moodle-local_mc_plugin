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

import Templates from 'core/templates';
import {exception as displayException} from 'core/notification';

/**
 * Render the connection status into a container.
 *
 * Uses the connection_status_dynamic template for client-side rendering
 * after API calls to update the status display.
 *
 * @param {HTMLElement} container The container element to render into
 * @param {Object} context The template context
 * @param {boolean} context.connected Whether the site is connected
 * @param {string} context.statusclass CSS class for status text
 * @param {string} context.dotclass CSS class for status dot
 * @param {string} context.statustext Status text to display
 * @param {string} [context.sitename] Connected site name
 * @param {boolean} [context.hassitename] Whether sitename is present
 * @param {string} [context.syncstatus] Sync status text
 * @param {boolean} [context.hassyncstatus] Whether syncstatus is present
 * @returns {Promise<void>}
 */
export const renderConnectionStatus = async(container, context) => {
    try {
        const {html, js} = await Templates.renderForPromise(
            'local_mc_plugin/connection_status_dynamic',
            context
        );
        Templates.replaceNodeContents(container, html, js);
    } catch (error) {
        displayException(error);
    }
};

/**
 * Render an action result message into a container.
 *
 * Uses the action_result template for client-side rendering
 * to display success or error messages after save/sync operations.
 *
 * @param {HTMLElement} container The container element to render into
 * @param {Object} context The template context
 * @param {boolean} context.success Whether the operation was successful
 * @param {string} context.message Message text to display
 * @param {string} context.alertclass CSS class for alert styling
 * @returns {Promise<void>}
 */
export const renderActionResult = async(container, context) => {
    try {
        const {html, js} = await Templates.renderForPromise(
            'local_mc_plugin/action_result',
            context
        );
        Templates.replaceNodeContents(container, html, js);
    } catch (error) {
        displayException(error);
    }
};

/**
 * Build context object for connection status template.
 *
 * Helper function to construct the context object from API response data.
 *
 * @param {boolean} connected Whether connected
 * @param {string} statusText Status text to display
 * @param {string|null} siteName Site name if connected
 * @param {string|null} syncStatus Sync status text
 * @returns {Object} Template context object
 */
export const buildConnectionStatusContext = (connected, statusText, siteName = null, syncStatus = null) => {
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
};

/**
 * Build context object for action result template.
 *
 * Helper function to construct the context object for result messages.
 *
 * @param {boolean} success Whether the operation was successful
 * @param {string} message Message text to display
 * @returns {Object} Template context object
 */
export const buildActionResultContext = (success, message) => {
    return {
        success: success,
        message: message,
        alertclass: success ? 'alert-success' : 'alert-danger',
    };
};
