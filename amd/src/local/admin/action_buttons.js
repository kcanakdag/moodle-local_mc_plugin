/**
 * Action buttons module for the admin settings page.
 *
 * Handles the Save & Sync button functionality using Mustache templates
 * for result message display.
 *
 * @module     local_mc_plugin/local/admin/action_buttons
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Selectors from './selectors';
import * as Repository from './repository';
import * as ConnectionStatus from './connection_status';
import * as TemplateHelper from './templates';
import {get_string as getString} from 'core/str';

/** @type {Object} Configuration */
let config = {};

/** @type {string} Button label */
let btnLabel = '';

/** @type {HTMLElement|null} Action buttons container */
let container = null;

/** @type {HTMLElement|null} Result message container */
let resultDiv = null;

/** @type {HTMLElement|null} Primary button */
let primaryBtn = null;

/** @type {HTMLElement|null} Button spinner */
let btnSpinner = null;

/** @type {HTMLElement|null} Button text element */
let btnTextEl = null;

/**
 * Show result message using template rendering.
 *
 * @param {boolean} success Whether successful
 * @param {string} message The message to display
 */
const showResult = async(success, message) => {
    if (!resultDiv) {
        return;
    }

    const context = TemplateHelper.buildActionResultContext(success, message);
    await TemplateHelper.renderActionResult(resultDiv, context);
};

/**
 * Clear the result message.
 */
const clearResult = () => {
    if (resultDiv) {
        resultDiv.innerHTML = '';
    }
};


/**
 * Set loading state on button.
 *
 * @param {boolean} loading Whether loading
 */
const setLoading = (loading) => {
    if (primaryBtn) {
        primaryBtn.disabled = loading;
    }
    if (btnSpinner) {
        if (loading) {
            btnSpinner.classList.remove('d-none');
        } else {
            btnSpinner.classList.add('d-none');
        }
    }
};

/**
 * Set button text.
 *
 * @param {string} text The text to display
 */
const setBtnText = (text) => {
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
        values.siteKey = siteKeyInput.value;
    }

    const siteSecretInput = document.querySelector(Selectors.inputs.siteSecret);
    if (siteSecretInput && siteSecretInput.value) {
        values.siteSecret = siteSecretInput.value;
    }

    const eventsInput = document.querySelector(Selectors.inputs.monitoredEvents);
    if (eventsInput) {
        values.monitoredEvents = eventsInput.value;
    }

    const debugInput = document.querySelector(Selectors.inputs.debugMode);
    if (debugInput) {
        values.debugMode = debugInput.checked ? 1 : 0;
    }

    return values;
};

/**
 * Handle the Save & Sync button click.
 */
const handleSaveSync = async() => {
    clearResult();
    setLoading(true);

    const savingText = await getString('btn_saving', 'local_mc_plugin');
    setBtnText(savingText);

    try {
        // Save settings first
        const values = getFormValues();
        const saveResult = await Repository.saveSettings(config.ajaxSaveUrl, config.sesskey, values);

        if (!saveResult.success) {
            setLoading(false);
            setBtnText(btnLabel);
            await showResult(false, saveResult.message || 'Failed to save settings');
            return;
        }

        // Then sync events
        const syncingText = await getString('btn_syncing', 'local_mc_plugin');
        setBtnText(syncingText);
        const syncResult = await Repository.syncEvents(config.syncUrl, config.sesskey);

        setLoading(false);
        setBtnText(btnLabel);

        if (syncResult.success) {
            const successMsg = await getString('sync_success', 'local_mc_plugin', syncResult.event_count || 0);
            await showResult(true, successMsg);
            ConnectionStatus.testConnection();
        } else {
            const failMsg = await getString('sync_failed', 'local_mc_plugin', syncResult.message);
            await showResult(false, failMsg);
            ConnectionStatus.updateStatusWithError(syncResult.message);
        }
    } catch (err) {
        setLoading(false);
        setBtnText(btnLabel);
        await showResult(false, `Error: ${err.message}`);
    }
};

/**
 * Initialize the action buttons module.
 *
 * Reads configuration from data attributes on the container element.
 *
 * @param {Object} [cfg] Optional configuration object (for backward compatibility)
 * @param {string} [cfg.syncUrl] URL to sync_schema.php
 * @param {string} [cfg.ajaxSaveUrl] URL to ajax_save.php
 * @param {string} [cfg.sesskey] Moodle session key
 */
export const init = async(cfg = null) => {
    // Find the action buttons container
    container = document.querySelector(Selectors.actions.container);

    if (container) {
        // Read config from data attributes
        config = {
            syncUrl: container.dataset.syncurl || (cfg && cfg.syncUrl) || '',
            ajaxSaveUrl: container.dataset.ajaxsaveurl || (cfg && cfg.ajaxSaveUrl) || '',
            sesskey: container.dataset.sesskey || (cfg && cfg.sesskey) || '',
        };

        // Find elements within container
        resultDiv = container.querySelector(Selectors.actions.resultDiv);
        primaryBtn = container.querySelector(Selectors.actions.primaryBtn);
        btnSpinner = container.querySelector(Selectors.actions.btnSpinner);
        btnTextEl = container.querySelector(Selectors.actions.btnText);
    } else if (cfg) {
        // Fallback to passed config and legacy selectors (backward compatibility)
        config = cfg;
        resultDiv = document.querySelector(Selectors.actions.legacyResultDiv);
        primaryBtn = document.querySelector(Selectors.actions.legacyPrimaryBtn);
        btnSpinner = document.querySelector(Selectors.actions.legacyBtnSpinner);
        btnTextEl = document.querySelector(Selectors.actions.legacyBtnText);
    }

    // Load button label string
    btnLabel = await getString('btn_save_sync', 'local_mc_plugin');

    if (primaryBtn) {
        primaryBtn.addEventListener('click', handleSaveSync);
    }
};
