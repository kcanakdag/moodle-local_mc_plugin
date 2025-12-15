/**
 * Repository module for AJAX calls to the backend.
 *
 * @module     local_mc_plugin/local/admin/repository
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Make a POST request with form data.
 *
 * @param {string} url The URL to post to
 * @param {Object} data The data to send
 * @returns {Promise<Object>} The JSON response
 */
const postForm = async(url, data) => {
    const params = new URLSearchParams();
    Object.entries(data).forEach(([key, value]) => {
        params.append(key, value);
    });

    const response = await fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString(),
    });

    return response.json();
};

/**
 * Initialize the connection flow (get a token).
 *
 * @param {string} connectUrl URL to connect.php
 * @param {string} sesskey Moodle session key
 * @returns {Promise<Object>} Response with token or error
 */
export const initConnection = async(connectUrl, sesskey) => {
    return postForm(connectUrl, {action: 'init', sesskey});
};

/**
 * Poll the MoodleConnect API for connection status.
 *
 * @param {string} apiUrl MoodleConnect API URL
 * @param {string} token Connection token
 * @returns {Promise<Object>} Status response
 */
export const pollConnectionStatus = async(apiUrl, token) => {
    const response = await fetch(`${apiUrl}/connect/status?token=${encodeURIComponent(token)}`);
    return response.json();
};

/**
 * Save credentials to Moodle settings.
 *
 * @param {string} saveUrl URL to ajax_save.php
 * @param {string} sesskey Moodle session key
 * @param {string} siteKey The site key
 * @param {string} siteSecret The site secret
 * @returns {Promise<Object>} Response with success status
 */
export const saveCredentials = async(saveUrl, sesskey, siteKey, siteSecret) => {
    return postForm(saveUrl, {
        action: 'save',
        sesskey,
        siteKey: siteKey,
        siteSecret: siteSecret,
    });
};

/**
 * Get connection status from the backend.
 *
 * @param {string} syncUrl URL to sync_schema.php
 * @param {string} sesskey Moodle session key
 * @returns {Promise<Object>} Status response
 */
export const getConnectionStatus = async(syncUrl, sesskey) => {
    return postForm(syncUrl, {action: 'status', sesskey});
};

/**
 * Save settings via AJAX.
 *
 * @param {string} ajaxSaveUrl URL to ajax_save.php
 * @param {string} sesskey Moodle session key
 * @param {Object} values Form values to save
 * @returns {Promise<Object>} Response with success status
 */
export const saveSettings = async(ajaxSaveUrl, sesskey, values) => {
    return postForm(ajaxSaveUrl, {action: 'save', sesskey, ...values});
};

/**
 * Sync events to MoodleConnect.
 *
 * @param {string} syncUrl URL to sync_schema.php
 * @param {string} sesskey Moodle session key
 * @returns {Promise<Object>} Response with success status and event count
 */
export const syncEvents = async(syncUrl, sesskey) => {
    return postForm(syncUrl, {action: 'sync', sesskey});
};
