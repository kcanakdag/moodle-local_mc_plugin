/**
 * Connection status module for the admin settings page.
 *
 * Handles status display and polling using Mustache templates
 * for dynamic UI updates.
 *
 * @module     local_mc_plugin/local/admin/connection_status
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Selectors from './selectors';
import * as Repository from './repository';
import * as EventSelector from './event_selector';
import * as TemplateHelper from './templates';
import {get_string as getString} from 'core/str';

/** @type {Object} Configuration */
let config = {};

/** @type {string} Event selector input ID for refreshing counter */
let eventInputId = '';

/** @type {HTMLElement|null} Status container element */
let statusContainer = null;

/** @type {HTMLElement|null} Status content element for template rendering */
let statusContent = null;

/**
 * Get the count of selected events from the form.
 *
 * @returns {number}
 */
const getSelectedEventCount = () => {
    const eventsInput = document.querySelector(Selectors.inputs.monitoredEvents);
    if (!eventsInput || !eventsInput.value) {
        return 0;
    }
    return eventsInput.value.split(',').filter((e) => e.trim() !== '').length;
};

/**
 * Get the list of selected events from the form.
 *
 * @returns {Array<string>}
 */
const getSelectedEvents = () => {
    const eventsInput = document.querySelector(Selectors.inputs.monitoredEvents);
    if (!eventsInput || !eventsInput.value) {
        return [];
    }
    return eventsInput.value.split(',').map((e) => e.trim()).filter((e) => e !== '');
};


/**
 * Build sync status text based on selected and synced events.
 *
 * @param {number} syncedCount Number of synced events
 * @param {Array} syncedEvents Array of synced event names
 * @returns {Promise<string|null>} Sync status text or null
 */
const buildSyncStatusText = async(syncedCount, syncedEvents) => {
    const selectedCount = getSelectedEventCount();
    const selectedEvents = getSelectedEvents();

    if (syncedCount === 0) {
        return await getString('status_events_not_synced', 'local_mc_plugin');
    }

    if (syncedCount === selectedCount && syncedEvents) {
        const allMatch = selectedEvents.every((e) => syncedEvents.includes(e));
        if (allMatch) {
            const str = await getString('status_events_synced', 'local_mc_plugin', syncedCount);
            return str;
        }
    }

    // Events changed
    return await getString('status_events_changed', 'local_mc_plugin');
};

/**
 * Update the status display using template rendering.
 *
 * @param {boolean} connected Whether connected
 * @param {string|null} siteName Site name if connected
 * @param {number} syncedCount Number of synced events
 * @param {Array} syncedEvents Array of synced event names
 * @param {string|null} message Error message if not connected
 */
const updateStatus = async(connected, siteName, syncedCount, syncedEvents, message) => {
    if (!statusContent) {
        return;
    }

    // Update event selector with synced events
    EventSelector.setSyncedEvents(syncedEvents);

    let statusText;
    let syncStatus = null;

    if (connected) {
        statusText = await getString('status_connected', 'local_mc_plugin');
        syncStatus = await buildSyncStatusText(syncedCount, syncedEvents);

        // Refresh event selector counter
        if (eventInputId) {
            EventSelector.refreshCounter(eventInputId);
        }
    } else {
        statusText = message || await getString('status_not_connected', 'local_mc_plugin');
    }

    // Build context and render template
    const context = TemplateHelper.buildConnectionStatusContext(
        connected,
        statusText,
        siteName,
        syncStatus
    );

    await TemplateHelper.renderConnectionStatus(statusContent, context);
};

/**
 * Test the connection and update status.
 *
 * @param {boolean} autoSync Whether to automatically sync events after connection check
 */
export const testConnection = async(autoSync = false) => {
    if (!statusContent) {
        return;
    }

    // Show loading state - render with checking text
    const checkingText = await getString('connect_initializing', 'local_mc_plugin');
    const loadingContext = {
        connected: false,
        statusclass: 'text-muted',
        dotclass: 'text-muted',
        statustext: checkingText,
        hassitename: false,
        hassyncstatus: false,
    };
    await TemplateHelper.renderConnectionStatus(statusContent, loadingContext);

    try {
        const data = await Repository.getConnectionStatus(config.syncUrl, config.sesskey);

        if (data.connected) {
            await updateStatus(true, data.site_name, data.synced_event_count || 0, data.synced_events || []);

            // Auto-sync events if requested and connected
            if (autoSync) {
                await syncEventsInBackground();
            }
        } else if (data.error) {
            await updateStatus(false, null, 0, [], data.error);
        } else if (data.configured) {
            const msg = await getString('status_click_connect', 'local_mc_plugin');
            await updateStatus(false, null, 0, [], msg);
        } else {
            const msg = await getString('status_click_connect_link', 'local_mc_plugin');
            await updateStatus(false, null, 0, [], msg);
        }
    } catch (err) {
        await updateStatus(false, null, 0, [], err.message);
    }
};

/**
 * Sync events in the background and update status.
 */
const syncEventsInBackground = async() => {
    try {
        const syncResult = await Repository.syncEvents(config.syncUrl, config.sesskey);

        if (syncResult.success) {
            // Refresh connection status to show updated sync count
            const data = await Repository.getConnectionStatus(config.syncUrl, config.sesskey);
            if (data.connected) {
                await updateStatus(true, data.site_name, data.synced_event_count || 0, data.synced_events || []);
            }
        }
        // Silently ignore sync failures - user can manually sync if needed
    } catch (err) {
        // Silently ignore errors - connection status already shows current state
    }
};

/**
 * Update status with an error message (for sync failures).
 *
 * @param {string} errorMessage The error message
 */
export const updateStatusWithError = async(errorMessage) => {
    if (!statusContent) {
        return;
    }

    const syncFailedText = await getString('status_sync_failed', 'local_mc_plugin');
    const context = {
        connected: false,
        statusclass: 'text-danger',
        dotclass: 'text-danger',
        statustext: syncFailedText,
        hassitename: false,
        syncstatus: errorMessage,
        hassyncstatus: true,
    };

    await TemplateHelper.renderConnectionStatus(statusContent, context);
};

/**
 * Initialize the connection status module.
 *
 * Reads configuration from data attributes on the container element.
 * Automatically syncs events on page load when connected.
 *
 * @param {Object} [cfg] Optional configuration object (for backward compatibility)
 * @param {string} [cfg.syncUrl] URL to sync_schema.php
 * @param {string} [cfg.sesskey] Moodle session key
 * @param {string} [cfg.eventInputId] Event selector input ID
 */
export const init = (cfg = null) => {
    // Find the status container
    statusContainer = document.querySelector(Selectors.status.container);

    if (statusContainer) {
        // Read config from data attributes
        config = {
            syncUrl: statusContainer.dataset.syncurl || (cfg && cfg.syncUrl) || '',
            sesskey: statusContainer.dataset.sesskey || (cfg && cfg.sesskey) || '',
        };
        eventInputId = statusContainer.dataset.eventinputid || (cfg && cfg.eventInputId) || '';

        // Find the content area for template rendering
        statusContent = statusContainer.querySelector(Selectors.status.content);
    } else if (cfg) {
        // Fallback to passed config (backward compatibility)
        config = cfg;
        eventInputId = cfg.eventInputId || '';
    }

    // Initial status check with auto-sync enabled
    // This syncs events automatically when the page loads
    testConnection(true);
};
