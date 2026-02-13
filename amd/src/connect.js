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
define([
    'local_mc_plugin/local/admin/selectors',
    'local_mc_plugin/local/admin/repository',
    'local_mc_plugin/local/admin/connection_status',
    'core/str'
], function(Selectors, Repository, ConnectionStatus, Str) {
    "use strict";

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
        const results = await Str.get_strings([
            {key: 'connect_initializing', component: 'local_mc_plugin'},
            {key: 'connect_waiting', component: 'local_mc_plugin'},
            {key: 'connect_waiting_btn', component: 'local_mc_plugin'},
            {key: 'connect_saving', component: 'local_mc_plugin'},
            {key: 'connect_success', component: 'local_mc_plugin'},
            {key: 'connect_popup_fallback', component: 'local_mc_plugin'},
            {key: 'connect_popup_open_link', component: 'local_mc_plugin'},
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
            popupFallback: results[5],
            popupOpenLink: results[6],
            initFailed: results[7],
            timeout: results[8],
            tokenExpired: results[9],
            credentialsRetrieved: results[10],
            saveFailed: results[11],
            connectBtn: results[12],
            reconnectBtn: results[13],
        };
    };

    /**
     * Find DOM elements using data-* attribute selectors.
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

                // Refresh connection status and sync all events
                setTimeout(() => {
                    ConnectionStatus.testConnection(true);
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
        } catch (e) {
            // Network error, but continue polling silently
        }
    };

    /**
     * Get the appropriate button text based on connection state.
     *
     * @returns {string} Button text
     */
    const getButtonText = () => {
        return isConnected ? strings.reconnectBtn : strings.connectBtn;
    };

    /**
     * Reset button state after connection attempt.
     */
    const resetButtonState = () => {
        setLoading(false);
        setBtnText(getButtonText());
    };

    /**
     * Handle successful token retrieval and open connection window.
     *
     * @param {Object} data Response data with token
     */
    const handleTokenSuccess = (data) => {
        currentToken = data.token;

        // Build the connect URL and attempt to open in a new tab.
        const connectPageUrl = `${config.frontendUrl}/connect?token=${encodeURIComponent(data.token)}`;
        window.open(connectPageUrl, '_blank');

        setBtnText(strings.waitingBtn);
        showStatus('waiting', strings.waiting);

        // Always append a fallback link in case the new tab didn't open (popup blockers, etc).
        if (statusText) {
            const fallback = document.createElement('span');
            fallback.style.display = 'block';
            fallback.style.marginTop = '0.5rem';
            fallback.textContent = strings.popupFallback + ' ';

            const link = document.createElement('a');
            link.href = connectPageUrl;
            link.target = '_blank';
            link.rel = 'noopener';
            link.textContent = strings.popupOpenLink;
            link.style.fontWeight = 'bold';

            fallback.appendChild(link);
            statusText.appendChild(fallback);
        }

        // Start polling.
        pollTimer = setInterval(pollStatus, POLL_INTERVAL);
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
                handleTokenSuccess(data);
            } else {
                resetButtonState();
                showStatus('error', data.message || strings.initFailed);
            }
        } catch (err) {
            resetButtonState();
            showStatus('error', `${strings.initFailed}: ${err.message}`);
        }
    };

    /**
     * Get config value from button data attribute or fallback config.
     *
     * @param {string} dataAttr Data attribute name (lowercase)
     * @param {string} cfgKey Config key name
     * @param {Object|null} cfg Fallback config object
     * @returns {string} Config value
     */
    const getConfigValue = (dataAttr, cfgKey, cfg) => {
        if (connectBtn && connectBtn.dataset[dataAttr]) {
            return connectBtn.dataset[dataAttr];
        }
        if (cfg && cfg[cfgKey]) {
            return cfg[cfgKey];
        }
        return '';
    };

    /**
     * Build configuration from button data attributes or fallback config.
     *
     * @param {Object|null} cfg Fallback config object
     * @returns {Object} Configuration object
     */
    const buildConfig = (cfg) => {
        return {
            connectUrl: getConfigValue('connecturl', 'connectUrl', cfg),
            saveUrl: getConfigValue('saveurl', 'saveUrl', cfg),
            apiUrl: getConfigValue('apiurl', 'apiUrl', cfg),
            frontendUrl: getConfigValue('frontendurl', 'frontendUrl', cfg),
            sesskey: getConfigValue('sesskey', 'sesskey', cfg),
        };
    };

    return {
        /**
         * Initialize the connect module.
         *
         * @param {Object} [cfg] Optional configuration object
         */
        init: async function(cfg) {
            cfg = cfg || null;
            await loadStrings();
            findElements();

            config = buildConfig(cfg);

            if (connectBtn) {
                isConnected = connectBtn.classList.contains('btn-outline-primary') || Boolean(cfg && cfg.isConnected);
                connectBtn.addEventListener('click', startConnection);
            } else if (cfg) {
                isConnected = Boolean(cfg.isConnected);
            }
        }
    };
});
