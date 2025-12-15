/**
 * MoodleConnect OAuth-style connection flow.
 *
 * This module handles:
 * - Opening the MoodleConnect tab with connection token
 * - Polling for connection completion
 * - Storing credentials on success
 *
 * Uses data-* attributes from connect_button.mustache template.
 *
 * @module     local_mc_plugin/connect
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Selectors from './local/admin/selectors';
import * as Repository from './local/admin/repository';
import * as ConnectionStatus from './local/admin/connection_status';
import {get_strings as getStrings} from 'core/str';

const POLL_INTERVAL = 3000; // 3 seconds
const MAX_POLL_ATTEMPTS = 60; // 3 minutes total

/** @type {Object} Configuration */
let config = {};

/** @type {Object} Language strings */
let strings = {};

/** @type {number|null} Poll timer ID */
let pollTimer = null;

/** @type {number} Poll attempt counter */
let pollAttempts = 0;

/** @type {string|null} Current connection token */
let currentToken = null;

/** @type {boolean} Whether currently connected */
let isConnected = false;

/** @type {HTMLElement|null} Connect button */
let connectBtn = null;

/** @type {HTMLElement|null} Button text element */
let btnText = null;

/** @type {HTMLElement|null} Button spinner element */
let btnSpinner = null;

/** @type {HTMLElement|null} Status div element */
let statusDiv = null;

/** @type {HTMLElement|null} Status icon element */
let statusIcon = null;

/** @type {HTMLElement|null} Status text element */
let statusText = null;


/**
 * Load language strings.
 *
 * @returns {Promise<void>}
 */
const loadStrings = async() => {
    const results = await getStrings([
        {key: 'connect_initializing', component: 'local_mc_plugin'},
        {key: 'connect_waiting', component: 'local_mc_plugin'},
        {key: 'connect_waiting_btn', component: 'local_mc_plugin'},
        {key: 'connect_saving', component: 'local_mc_plugin'},
        {key: 'connect_success', component: 'local_mc_plugin'},
        {key: 'connect_popup_blocked', component: 'local_mc_plugin'},
        {key: 'connect_init_failed', component: 'local_mc_plugin'},
        {key: 'connect_timeout', component: 'local_mc_plugin'},
        {key: 'connect_token_expired', component: 'local_mc_plugin'},
        {key: 'connect_credentials_retrieved', component: 'local_mc_plugin'},
        {key: 'connect_save_failed', component: 'local_mc_plugin'},
        {key: 'connect_button', component: 'local_mc_plugin'},
        {key: 'reconnect_button', component: 'local_mc_plugin'},
    ]);

    strings = {
        initializing: results[0],
        waiting: results[1],
        waitingBtn: results[2],
        saving: results[3],
        success: results[4],
        popupBlocked: results[5],
        initFailed: results[6],
        timeout: results[7],
        tokenExpired: results[8],
        credentialsRetrieved: results[9],
        saveFailed: results[10],
        connectBtn: results[11],
        reconnectBtn: results[12],
    };
};

/**
 * Find DOM elements using data-* attribute selectors.
 *
 * Falls back to legacy ID-based selectors for backward compatibility.
 */
const findElements = () => {
    // Try data-* attribute selectors first (from template)
    const container = document.querySelector(Selectors.connect.container);

    if (container) {
        connectBtn = container.querySelector(Selectors.connect.button);
        btnText = container.querySelector(Selectors.connect.buttonText);
        btnSpinner = container.querySelector(Selectors.connect.buttonSpinner);
        statusDiv = container.querySelector(Selectors.connect.statusDiv);
        statusIcon = container.querySelector(Selectors.connect.statusIcon);
        statusText = container.querySelector(Selectors.connect.statusText);
    } else {
        // Fallback to legacy ID-based selectors
        connectBtn = document.querySelector(Selectors.connect.legacyButton);
        btnText = document.querySelector(Selectors.connect.legacyButtonText);
        btnSpinner = document.querySelector(Selectors.connect.legacyButtonSpinner);
        statusDiv = document.querySelector(Selectors.connect.legacyStatusDiv);
        statusIcon = document.querySelector(Selectors.connect.legacyStatusIcon);
        statusText = document.querySelector(Selectors.connect.legacyStatusText);
    }
};

/**
 * Show status message.
 *
 * @param {string} type Status type: 'waiting', 'success', 'error'
 * @param {string} message Status message
 */
const showStatus = (type, message) => {
    if (!statusDiv) {
        return;
    }

    // Show the status div
    statusDiv.classList.remove('d-none');
    statusDiv.style.display = 'block';

    // Remove previous alert classes
    statusDiv.classList.remove('alert-warning', 'alert-success', 'alert-danger');

    if (type === 'waiting') {
        statusDiv.classList.add('alert-warning');
        if (statusIcon) {
            statusIcon.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>';
        }
    } else if (type === 'success') {
        statusDiv.classList.add('alert-success');
        if (statusIcon) {
            statusIcon.innerHTML = '✓ ';
        }
    } else if (type === 'error') {
        statusDiv.classList.add('alert-danger');
        if (statusIcon) {
            statusIcon.innerHTML = '✗ ';
        }
    }

    if (statusText) {
        statusText.textContent = message;
    }
};

/**
 * Hide status message.
 */
const hideStatus = () => {
    if (statusDiv) {
        statusDiv.classList.add('d-none');
        statusDiv.style.display = 'none';
    }
};

/**
 * Set loading state.
 *
 * @param {boolean} loading Whether loading
 */
const setLoading = (loading) => {
    if (connectBtn) {
        connectBtn.disabled = loading;
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
 * @param {string} text Button text
 */
const setBtnText = (text) => {
    if (btnText) {
        btnText.textContent = text;
    }
};

/**
 * Stop polling.
 */
const stopPolling = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
};

/**
 * Save credentials after successful connection.
 *
 * @param {string} siteKey Site key
 * @param {string} siteSecret Site secret
 */
const saveCredentials = async(siteKey, siteSecret) => {
    showStatus('waiting', strings.saving);

    try {
        const data = await Repository.saveCredentials(config.saveUrl, config.sesskey, siteKey, siteSecret);

        setLoading(false);

        if (data.success) {
            showStatus('success', strings.success);
            setBtnText(strings.reconnectBtn);

            if (connectBtn) {
                connectBtn.classList.remove('btn-primary');
                connectBtn.classList.add('btn-outline-primary');
            }

            isConnected = true;

            // Refresh connection status
            setTimeout(() => {
                ConnectionStatus.testConnection();
            }, 500);
        } else {
            showStatus('error', data.message || strings.saveFailed);
        }
    } catch (err) {
        setLoading(false);
        showStatus('error', `${strings.saveFailed}: ${err.message}`);
    }
};

/**
 * Poll for connection status.
 */
const pollStatus = async() => {
    pollAttempts++;

    if (pollAttempts > MAX_POLL_ATTEMPTS) {
        stopPolling();
        setLoading(false);
        showStatus('error', strings.timeout);
        return;
    }

    try {
        const data = await Repository.pollConnectionStatus(config.apiUrl, currentToken);

        if (data.status === 'completed') {
            stopPolling();

            if (data.site_key && data.site_secret) {
                saveCredentials(data.site_key, data.site_secret);
            } else {
                setLoading(false);
                showStatus('error', strings.credentialsRetrieved);
            }
        } else if (data.status === 'expired') {
            stopPolling();
            setLoading(false);
            showStatus('error', strings.tokenExpired);
        }
        // If pending, continue polling
    } catch {
        // Network error, but continue polling silently
    }
};

/**
 * Start the connection flow.
 */
const startConnection = async() => {
    hideStatus();
    setLoading(true);
    setBtnText(strings.initializing);
    pollAttempts = 0;

    try {
        const data = await Repository.initConnection(config.connectUrl, config.sesskey);

        if (data.success && data.token) {
            currentToken = data.token;

            // Open MoodleConnect in a new tab
            const connectPageUrl = `${config.frontendUrl}/connect?token=${encodeURIComponent(data.token)}`;
            const connectWindow = window.open(connectPageUrl, '_blank');

            if (!connectWindow) {
                setLoading(false);
                setBtnText(isConnected ? strings.reconnectBtn : strings.connectBtn);
                showStatus('error', strings.popupBlocked);
                return;
            }

            setBtnText(strings.waitingBtn);
            showStatus('waiting', strings.waiting);

            // Start polling
            pollTimer = setInterval(pollStatus, POLL_INTERVAL);
        } else {
            setLoading(false);
            setBtnText(isConnected ? strings.reconnectBtn : strings.connectBtn);
            showStatus('error', data.message || strings.initFailed);
        }
    } catch (err) {
        setLoading(false);
        setBtnText(isConnected ? strings.reconnectBtn : strings.connectBtn);
        showStatus('error', `${strings.initFailed}: ${err.message}`);
    }
};

/**
 * Initialize the connect module.
 *
 * Reads configuration from data attributes on the connect button,
 * or accepts a configuration object for backward compatibility.
 *
 * @param {Object} [cfg] Optional configuration object
 * @param {string} [cfg.connectUrl] URL to connect.php
 * @param {string} [cfg.saveUrl] URL to ajax_save.php
 * @param {string} [cfg.apiUrl] MoodleConnect API URL
 * @param {string} [cfg.frontendUrl] MoodleConnect frontend URL
 * @param {string} [cfg.sesskey] Moodle session key
 * @param {boolean} [cfg.isConnected] Whether already connected
 */
export const init = async(cfg = null) => {
    await loadStrings();

    // Find DOM elements
    findElements();

    if (connectBtn) {
        // Read config from data attributes on the button
        config = {
            connectUrl: connectBtn.dataset.connecturl || (cfg && cfg.connectUrl) || '',
            saveUrl: connectBtn.dataset.saveurl || (cfg && cfg.saveUrl) || '',
            apiUrl: connectBtn.dataset.apiurl || (cfg && cfg.apiUrl) || '',
            frontendUrl: connectBtn.dataset.frontendurl || (cfg && cfg.frontendUrl) || '',
            sesskey: connectBtn.dataset.sesskey || (cfg && cfg.sesskey) || '',
        };

        // Determine initial connection state from button class
        isConnected = connectBtn.classList.contains('btn-outline-primary') || (cfg && cfg.isConnected) || false;

        connectBtn.addEventListener('click', startConnection);
    } else if (cfg) {
        // Fallback to passed config (backward compatibility)
        config = cfg;
        isConnected = cfg.isConnected || false;
    }
};
