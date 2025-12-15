/**
 * Connection status module for the admin settings page.
 *
 * Handles status display and polling.
 *
 * @module     local_mc_plugin/local/admin/connection_status
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Selectors from './selectors';
import * as Repository from './repository';
import * as EventSelector from './event_selector';

/** @type {Object} Configuration */
let config = {};

/** @type {string} Event selector input ID for refreshing counter */
let eventInputId = '';

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
 * Update the status display.
 *
 * @param {boolean} connected Whether connected
 * @param {string|null} siteName Site name if connected
 * @param {number} syncedCount Number of synced events
 * @param {Array} syncedEvents Array of synced event names
 * @param {string|null} message Error message if not connected
 */
const updateStatus = (connected, siteName, syncedCount, syncedEvents, message) => {
    const statusDot = document.querySelector(Selectors.status.dot);
    const statusText = document.querySelector(Selectors.status.text);
    const siteNameEl = document.querySelector(Selectors.status.siteName);
    const syncStatus = document.querySelector(Selectors.status.syncStatus);
    const testResult = document.querySelector(Selectors.status.testResult);

    if (!statusDot || !statusText) {
        return;
    }

    // Update event selector with synced events
    EventSelector.setSyncedEvents(syncedEvents);

    if (connected) {
        statusDot.style.color = '#28a745';
        statusText.style.color = '#155724';
        statusText.textContent = 'Connected';

        if (siteName && siteNameEl) {
            siteNameEl.textContent = `(${siteName})`;
        }

        if (testResult) {
            testResult.innerHTML = '';
        }

        const selectedCount = getSelectedEventCount();
        const selectedEvents = getSelectedEvents();

        if (syncStatus) {
            if (syncedCount === 0) {
                syncStatus.innerHTML = '<span style="color: #856404;">• Events not synced yet</span>';
            } else if (syncedCount === selectedCount && syncedEvents) {
                const allMatch = selectedEvents.every((e) => syncedEvents.includes(e));
                if (allMatch) {
                    syncStatus.innerHTML = `<span style="color: #155724;">• ${syncedCount} events synced</span>`;
                } else {
                    syncStatus.innerHTML = '<span style="color: #856404;">• Events changed, click Save & Sync</span>';
                }
            } else {
                const diff = selectedCount - syncedCount;
                if (diff > 0) {
                    syncStatus.innerHTML = `<span style="color: #856404;">• ${diff} new event(s) to sync</span>`;
                } else {
                    syncStatus.innerHTML = '<span style="color: #856404;">• Events changed, click Save & Sync</span>';
                }
            }
        }

        // Refresh event selector counter
        if (eventInputId) {
            EventSelector.refreshCounter(eventInputId);
        }
    } else {
        statusDot.style.color = '#dc3545';
        statusText.style.color = '#721c24';
        statusText.textContent = 'Not connected';

        if (siteNameEl) {
            siteNameEl.textContent = '';
        }
        if (syncStatus) {
            syncStatus.textContent = '';
        }
        if (testResult) {
            testResult.innerHTML = `<span style="color: #dc3545;">${message || 'Connection failed'}</span>`;
        }
    }
};

/**
 * Test the connection and update status.
 */
export const testConnection = async() => {
    const statusDot = document.querySelector(Selectors.status.dot);
    const statusText = document.querySelector(Selectors.status.text);
    const syncStatus = document.querySelector(Selectors.status.syncStatus);
    const testResult = document.querySelector(Selectors.status.testResult);

    if (statusDot) {
        statusDot.style.color = '#6c757d';
    }
    if (statusText) {
        statusText.style.color = '#6c757d';
        statusText.textContent = 'Checking...';
    }
    if (syncStatus) {
        syncStatus.textContent = '';
    }
    if (testResult) {
        testResult.innerHTML = '';
    }

    try {
        const data = await Repository.getConnectionStatus(config.syncUrl, config.sesskey);

        if (data.connected) {
            updateStatus(true, data.site_name, data.synced_event_count || 0, data.synced_events || []);
        } else if (data.error) {
            updateStatus(false, null, 0, [], data.error);
        } else if (data.configured) {
            updateStatus(false, null, 0, [], 'Click Connect to link your site');
        } else {
            updateStatus(false, null, 0, [], 'Click Connect to link your Moodle site');
        }
    } catch (err) {
        updateStatus(false, null, 0, [], err.message);
    }
};

/**
 * Update status with an error message (for sync failures).
 *
 * @param {string} errorMessage The error message
 */
export const updateStatusWithError = (errorMessage) => {
    const statusDot = document.querySelector(Selectors.status.dot);
    const statusText = document.querySelector(Selectors.status.text);
    const syncStatus = document.querySelector(Selectors.status.syncStatus);

    if (statusDot) {
        statusDot.style.color = '#dc3545';
    }
    if (statusText) {
        statusText.style.color = '#721c24';
        statusText.textContent = 'Sync failed';
    }
    if (syncStatus) {
        syncStatus.innerHTML = `<span style="color: #dc3545;">• ${errorMessage}</span>`;
    }
};

/**
 * Initialize the connection status module.
 *
 * @param {Object} cfg Configuration object
 * @param {string} cfg.syncUrl URL to sync_schema.php
 * @param {string} cfg.sesskey Moodle session key
 * @param {string} cfg.eventInputId Event selector input ID
 */
export const init = (cfg) => {
    config = cfg;
    eventInputId = cfg.eventInputId || '';

    // Initial status check
    testConnection();
};
