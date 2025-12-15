/**
 * Action buttons module for the admin settings page.
 *
 * Handles the Save & Sync button functionality.
 *
 * @module     local_mc_plugin/local/admin/action_buttons
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Selectors from './selectors';
import * as Repository from './repository';
import * as ConnectionStatus from './connection_status';
import {get_string as getString} from 'core/str';

/** @type {Object} Configuration */
let config = {};

/** @type {string} Button label */
let btnLabel = '';

/**
 * Show result message.
 *
 * @param {boolean} success Whether successful
 * @param {string} message The message to display
 */
const showResult = (success, message) => {
    const resultDiv = document.querySelector(Selectors.actions.resultDiv);
    if (!resultDiv) {
        return;
    }

    resultDiv.style.display = 'block';
    resultDiv.style.background = success ? '#d4edda' : '#f8d7da';
    resultDiv.style.color = success ? '#155724' : '#721c24';
    resultDiv.innerHTML = (success ? '✓ ' : '✗ ') + message;
};

/**
 * Set loading state on button.
 *
 * @param {boolean} loading Whether loading
 */
const setLoading = (loading) => {
    const primaryBtn = document.querySelector(Selectors.actions.primaryBtn);
    const btnSpinner = document.querySelector(Selectors.actions.btnSpinner);

    if (primaryBtn) {
        primaryBtn.disabled = loading;
    }
    if (btnSpinner) {
        btnSpinner.style.display = loading ? 'inline-block' : 'none';
    }
};

/**
 * Set button text.
 *
 * @param {string} text The text to display
 */
const setBtnText = (text) => {
    const btnTextEl = document.querySelector(Selectors.actions.btnText);
    if (btnTextEl) {
        btnTextEl.textContent = text;
    }
};

/**
 * Get form values for saving.
 *
 * @returns {Object} Form values
 */
const getFormValues = () => {
    const values = {};

    const siteKeyInput = document.querySelector(Selectors.inputs.siteKey);
    if (siteKeyInput && siteKeyInput.value) {
        values.site_key = siteKeyInput.value;
    }

    const siteSecretInput = document.querySelector(Selectors.inputs.siteSecret);
    if (siteSecretInput && siteSecretInput.value) {
        values.site_secret = siteSecretInput.value;
    }

    const eventsInput = document.querySelector(Selectors.inputs.monitoredEvents);
    if (eventsInput) {
        values.monitored_events = eventsInput.value;
    }

    const debugInput = document.querySelector(Selectors.inputs.debugMode);
    if (debugInput) {
        values.debug_mode = debugInput.checked ? 1 : 0;
    }

    return values;
};

/**
 * Handle the Save & Sync button click.
 */
const handleSaveSync = async() => {
    const resultDiv = document.querySelector(Selectors.actions.resultDiv);
    if (resultDiv) {
        resultDiv.style.display = 'none';
    }

    setLoading(true);
    setBtnText('Saving...');

    try {
        // Save settings first
        const values = getFormValues();
        const saveResult = await Repository.saveSettings(config.ajaxSaveUrl, config.sesskey, values);

        if (!saveResult.success) {
            setLoading(false);
            setBtnText(btnLabel);
            showResult(false, saveResult.message || 'Failed to save settings');
            return;
        }

        // Then sync events
        setBtnText('Syncing...');
        const syncResult = await Repository.syncEvents(config.syncUrl, config.sesskey);

        setLoading(false);
        setBtnText(btnLabel);

        if (syncResult.success) {
            showResult(true, `Settings saved. Synced ${syncResult.event_count || 0} event(s) to MoodleConnect`);
            ConnectionStatus.testConnection();
        } else {
            showResult(false, `Settings saved, but sync failed: ${syncResult.message}`);
            ConnectionStatus.updateStatusWithError(syncResult.message);
        }
    } catch (err) {
        setLoading(false);
        setBtnText(btnLabel);
        showResult(false, `Error: ${err.message}`);
    }
};

/**
 * Initialize the action buttons module.
 *
 * @param {Object} cfg Configuration object
 * @param {string} cfg.syncUrl URL to sync_schema.php
 * @param {string} cfg.ajaxSaveUrl URL to ajax_save.php
 * @param {string} cfg.sesskey Moodle session key
 */
export const init = async(cfg) => {
    config = cfg;

    // Load button label string
    btnLabel = await getString('btn_save_sync', 'local_mc_plugin');

    const primaryBtn = document.querySelector(Selectors.actions.primaryBtn);
    if (primaryBtn) {
        primaryBtn.addEventListener('click', handleSaveSync);
    }
};
