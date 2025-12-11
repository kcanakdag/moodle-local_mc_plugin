/**
 * MoodleConnect OAuth-style connection flow.
 *
 * This module handles:
 * - Opening the MoodleConnect tab with connection token
 * - Polling for connection completion
 * - Storing credentials on success
 *
 * @module     local_mc_plugin/connect
 */

define(['jquery'], function($) {

    var POLL_INTERVAL = 3000; // 3 seconds.
    var MAX_POLL_ATTEMPTS = 60; // 3 minutes total.

    var pollTimer = null;
    var pollAttempts = 0;
    var currentToken = null;
    var connectWindow = null;

    /**
     * Get the MoodleConnect frontend URL from the API URL.
     * Converts https://moodleconnect.com/api to https://moodleconnect.com
     *
     * @param {string} apiUrl The API URL
     * @return {string} The frontend URL
     */
    function getFrontendUrl(apiUrl) {
        // Remove /api suffix to get frontend URL.
        return apiUrl.replace(/\/api\/?$/, '');
    }

    /**
     * Initialize the connection flow.
     *
     * @param {Object} config Configuration object
     * @param {string} config.connectUrl URL to connect.php AJAX endpoint
     * @param {string} config.statusUrl URL to check connection status
     * @param {string} config.saveUrl URL to save credentials
     * @param {string} config.apiUrl MoodleConnect API URL
     * @param {string} config.sesskey Moodle session key
     * @param {Function} config.onStatusChange Callback for status changes
     * @param {Function} config.onSuccess Callback for successful connection
     * @param {Function} config.onError Callback for errors
     */
    function init(config) {
        return {
            startConnection: function() {
                startConnection(config);
            },
            stopPolling: stopPolling
        };
    }

    /**
     * Start the connection flow.
     *
     * @param {Object} config Configuration object
     */
    function startConnection(config) {
        // Reset state.
        stopPolling();
        pollAttempts = 0;

        config.onStatusChange('initializing', M.util.get_string('connect_initializing', 'local_mc_plugin'));

        // Request a connection token from our backend.
        $.ajax({
            url: config.connectUrl,
            method: 'POST',
            data: {
                action: 'init',
                sesskey: config.sesskey
            },
            dataType: 'json'
        }).done(function(response) {
            if (response.success && response.token) {
                currentToken = response.token;

                // Open MoodleConnect in a new tab.
                var frontendUrl = getFrontendUrl(config.apiUrl);
                var connectPageUrl = frontendUrl + '/connect?token=' + encodeURIComponent(response.token);

                connectWindow = window.open(connectPageUrl, '_blank');

                if (!connectWindow) {
                    config.onError(M.util.get_string('connect_popup_blocked', 'local_mc_plugin'));
                    return;
                }

                config.onStatusChange('waiting', M.util.get_string('connect_waiting', 'local_mc_plugin'));

                // Start polling for completion.
                startPolling(config);
            } else {
                config.onError(response.message || M.util.get_string('connect_init_failed', 'local_mc_plugin'));
            }
        }).fail(function(xhr, status, error) {
            config.onError(M.util.get_string('connect_init_failed', 'local_mc_plugin') + ': ' + error);
        });
    }

    /**
     * Start polling for connection completion.
     *
     * @param {Object} config Configuration object
     */
    function startPolling(config) {
        pollTimer = setInterval(function() {
            pollAttempts++;

            if (pollAttempts > MAX_POLL_ATTEMPTS) {
                stopPolling();
                config.onError(M.util.get_string('connect_timeout', 'local_mc_plugin'));
                return;
            }

            // Poll the MoodleConnect API for status.
            $.ajax({
                url: config.apiUrl + '/connect/status',
                method: 'GET',
                data: {
                    token: currentToken
                },
                dataType: 'json'
            }).done(function(response) {
                if (response.status === 'completed') {
                    stopPolling();

                    if (response.site_key && response.site_secret) {
                        // Save credentials.
                        saveCredentials(config, response.site_key, response.site_secret);
                    } else {
                        // Credentials already retrieved.
                        config.onError(M.util.get_string('connect_credentials_retrieved', 'local_mc_plugin'));
                    }
                } else if (response.status === 'expired') {
                    stopPolling();
                    config.onError(M.util.get_string('connect_token_expired', 'local_mc_plugin'));
                } else if (response.status === 'pending') {
                    // Still waiting, continue polling.
                    config.onStatusChange('waiting', M.util.get_string('connect_waiting', 'local_mc_plugin'));
                }
            }).fail(function() {
                // Network error, but don't stop polling yet.
                // Silently continue polling.
            });
        }, POLL_INTERVAL);
    }

    /**
     * Stop the polling timer.
     */
    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    /**
     * Save credentials to Moodle settings.
     *
     * @param {Object} config Configuration object
     * @param {string} siteKey The site key
     * @param {string} siteSecret The site secret
     */
    function saveCredentials(config, siteKey, siteSecret) {
        config.onStatusChange('saving', M.util.get_string('connect_saving', 'local_mc_plugin'));

        $.ajax({
            url: config.saveUrl,
            method: 'POST',
            data: {
                action: 'save',
                sesskey: config.sesskey,
                siteKey: siteKey,
                siteSecret: siteSecret
            },
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                config.onSuccess(siteKey);
            } else {
                config.onError(response.message || M.util.get_string('connect_save_failed', 'local_mc_plugin'));
            }
        }).fail(function(xhr, status, error) {
            config.onError(M.util.get_string('connect_save_failed', 'local_mc_plugin') + ': ' + error);
        });
    }

    return {
        init: init
    };
});
