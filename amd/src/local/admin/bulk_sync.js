/**
 * Bulk sync module for firing user_updated events for all active users.
 *
 * @module     local_mc_plugin/local/admin/bulk_sync
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'local_mc_plugin/local/admin/repository',
    'core/str',
    'core/notification'
], function(Repository, Str, Notification) {
    "use strict";

    const BATCH_SIZE = 25;

    /** @type {Object} Configuration */
    let config = {};

    /** @type {number} Total user count from preflight */
    let totalCount = 0;

    /** @type {Object|null} Quota info from preflight */
    let quotaInfo = null;

    /**
     * Initialize the bulk sync component.
     *
     * @param {Object} cfg Configuration object
     * @param {string} cfg.syncUrl URL to sync_schema.php
     * @param {string} cfg.sesskey Moodle session key
     */
    const init = async function(cfg) {
        config = cfg;

        const container = document.getElementById('mc-bulk-sync');
        if (!container) {
            return;
        }

        // Run preflight to check status.
        await runPreflight();

        // Attach click handler.
        const btn = document.getElementById('mc-bulk-sync-btn');
        if (btn) {
            btn.addEventListener('click', handleClick);
        }
    };

    /**
     * Show a styled status message.
     *
     * @param {HTMLElement} el Container element
     * @param {string} text Message text
     * @param {string} className CSS class for the span (e.g., 'text-warning', 'text-danger')
     */
    const showStatus = (el, text, className) => {
        if (!el) {
            return;
        }
        el.textContent = '';
        const span = document.createElement('span');
        span.className = className;
        span.textContent = text;
        el.appendChild(span);
    };

    /**
     * Run preflight check to get user count and monitored status.
     */
    const runPreflight = async() => {
        const statusEl = document.getElementById('mc-bulk-sync-status');
        const btn = document.getElementById('mc-bulk-sync-btn');

        try {
            const result = await Repository.bulkSyncCount(config.syncUrl, config.sesskey);

            if (!result.success) {
                showStatus(statusEl, result.message, 'text-danger');
                return;
            }

            totalCount = result.count;
            quotaInfo = result.quota || null;

            if (!result.monitored) {
                const noTriggerMsg = await Str.get_string('bulk_sync_no_trigger', 'local_mc_plugin');
                showStatus(statusEl, noTriggerMsg, 'text-warning');
                return;
            }

            // Show user count and enable button.
            if (statusEl) {
                const statusMsg = await Str.get_string('bulk_sync_status_users', 'local_mc_plugin', totalCount);
                statusEl.textContent = statusMsg;
            }
            if (btn) {
                btn.disabled = false;
            }
        } catch (err) {
            const failMsg = await Str.get_string('bulk_sync_failed_preflight', 'local_mc_plugin');
            showStatus(statusEl, failMsg, 'text-danger');
        }
    };

    /**
     * Handle the Sync All Users button click.
     */
    const handleClick = async() => {
        // Pre-fetch all strings in a single batch call.
        const stringRequests = [
            {key: 'bulk_sync_confirm', component: 'local_mc_plugin', param: totalCount},
            {key: 'bulk_sync_experimental', component: 'local_mc_plugin'},
            {key: 'bulk_sync_button', component: 'local_mc_plugin'},
            {key: 'cancel', component: 'core'},
        ];

        const hasQuota = quotaInfo && quotaInfo.limit > 0;
        const afterSync = hasQuota ? quotaInfo.used + totalCount : 0;

        // Track where dynamic strings start.
        let dynamicIdx = stringRequests.length;

        if (hasQuota) {
            stringRequests.push({
                key: 'bulk_sync_quota_info',
                component: 'local_mc_plugin',
                param: {used: quotaInfo.used, limit: quotaInfo.limit, after: afterSync},
            });
            if (afterSync > quotaInfo.limit) {
                stringRequests.push({key: 'bulk_sync_quota_warning', component: 'local_mc_plugin'});
            }
        }

        const strings = await Str.get_strings(stringRequests);

        // Build confirmation body with quota details.
        let bodyParts = [strings[0]];
        let idx = dynamicIdx;
        if (hasQuota) {
            bodyParts.push(strings[idx++]);
            if (afterSync > quotaInfo.limit) {
                bodyParts.push(strings[idx]);
            }
        }

        Notification.confirm(
            strings[1], // Experimental title.
            bodyParts.join('<br><br>'),
            strings[2], // Confirm button.
            strings[3], // Cancel button.
            startBulkSync
        );
    };

    /**
     * Show a result message using a Bootstrap alert.
     *
     * @param {HTMLElement} el Result container element
     * @param {boolean} success Whether successful
     * @param {string} message The message to display
     */
    const showResult = (el, success, message) => {
        if (!el) {
            return;
        }
        el.textContent = '';
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert ' + (success ? 'alert-success' : 'alert-danger') + ' mt-2 mb-0 py-2 px-3 small';
        alertDiv.textContent = message;
        el.appendChild(alertDiv);
    };

    /**
     * Start the bulk sync process.
     */
    const startBulkSync = async() => {
        const btn = document.getElementById('mc-bulk-sync-btn');
        const spinner = document.getElementById('mc-bulk-sync-spinner');
        const progressContainer = document.getElementById('mc-bulk-sync-progress');
        const progressBar = document.getElementById('mc-bulk-sync-bar');
        const progressText = document.getElementById('mc-bulk-sync-progress-text');
        const resultEl = document.getElementById('mc-bulk-sync-result');

        // Disable button, show progress.
        if (btn) {
            btn.disabled = true;
        }
        if (spinner) {
            spinner.classList.remove('d-none');
        }
        if (progressContainer) {
            progressContainer.classList.remove('d-none');
        }
        if (resultEl) {
            resultEl.textContent = '';
        }

        let offset = 0;
        let totalProcessed = 0;

        try {
            while (true) { // eslint-disable-line no-constant-condition
                const result = await Repository.bulkSyncFire(config.syncUrl, config.sesskey, offset, BATCH_SIZE);

                if (!result.success) {
                    const errorMsg = await Str.get_string('bulk_sync_error', 'local_mc_plugin', totalProcessed);
                    showResult(resultEl, false, errorMsg);
                    break;
                }

                totalProcessed += result.processed;
                offset += BATCH_SIZE;

                // Update progress bar.
                const percent = totalCount > 0 ? Math.min(Math.round((totalProcessed / totalCount) * 100), 100) : 0;
                if (progressBar) {
                    progressBar.style.width = percent + '%';
                    progressBar.textContent = percent + '%';
                    progressBar.setAttribute('aria-valuenow', percent);
                }
                if (progressText) {
                    const progressMsg = await Str.get_string('bulk_sync_progress', 'local_mc_plugin', {
                        current: totalProcessed,
                        total: totalCount,
                    });
                    progressText.textContent = progressMsg;
                }

                if (!result.has_more) {
                    const successMsg = await Str.get_string('bulk_sync_success', 'local_mc_plugin', totalProcessed);
                    showResult(resultEl, true, successMsg);
                    break;
                }
            }
        } catch (err) {
            const errorMsg = await Str.get_string('bulk_sync_error', 'local_mc_plugin', totalProcessed);
            showResult(resultEl, false, errorMsg);
        }

        // Reset button state.
        if (spinner) {
            spinner.classList.add('d-none');
        }
        if (btn) {
            btn.disabled = false;
        }
    };

    return {init};
});
