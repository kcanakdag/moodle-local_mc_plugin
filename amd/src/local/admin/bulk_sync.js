/**
 * Bulk sync module for firing user_updated events for all active users.
 *
 * @module     local_mc_plugin/local/admin/bulk_sync
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'local_mc_plugin/local/admin/repository',
    'local_mc_plugin/local/admin/selectors',
    'core/str',
    'core/notification'
], function(Repository, Selectors, Str, Notification) {
    "use strict";

    const BATCH_SIZE = 25;
    const MAX_SKIPPED_DISPLAY = 100;
    const SEL = Selectors.bulkSync;

    /** @type {Object} Configuration */
    let config = {};

    /** @type {number} Total user count from preflight */
    let totalCount = 0;

    /** @type {Object|null} Quota info from preflight */
    let quotaInfo = null;

    /** @type {boolean} Whether a sync is currently running */
    let isRunning = false;

    /** @type {boolean} Whether the user has requested cancellation */
    let cancelled = false;

    /** @type {AbortController|null} Controls the cancel button listener lifecycle */
    let cancelController = null;

    /** @type {HTMLElement|null} Cached container element, set on first init */
    let container = null;

    /**
     * Initialize the bulk sync component.
     *
     * @param {Object} cfg Configuration object
     * @param {string} cfg.syncUrl URL to sync_schema.php
     * @param {string} cfg.sesskey Moodle session key
     */
    const init = async function(cfg) {
        if (container) {
            return; // Already initialized — prevent duplicate listeners on re-init.
        }
        config = cfg;

        container = document.querySelector(SEL.container);
        if (!container) {
            return;
        }

        await runPreflight(container);
    };

    /**
     * Show a styled status message.
     *
     * @param {HTMLElement} el Container element
     * @param {string} text Message text
     * @param {string} className CSS class for the span
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
     * Run preflight check to get user count and monitored status.
     *
     * @param {HTMLElement} container The bulk sync container element
     */
    const runPreflight = async(container) => {
        const statusEl = container.querySelector(SEL.status);
        const btn = container.querySelector(SEL.startBtn);

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

            if (statusEl) {
                const statusMsg = await Str.get_string('bulk_sync_status_users', 'local_mc_plugin', totalCount);
                statusEl.textContent = statusMsg;
            }
            if (btn) {
                btn.disabled = false;
                btn.addEventListener('click', handleClick);
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
        if (isRunning) {
            return;
        }

        const stringRequests = [
            {key: 'bulk_sync_confirm', component: 'local_mc_plugin', param: totalCount},
            {key: 'bulk_sync_experimental', component: 'local_mc_plugin'},
            {key: 'bulk_sync_button', component: 'local_mc_plugin'},
            {key: 'cancel', component: 'core'},
        ];

        const hasQuota = quotaInfo && quotaInfo.limit > 0;
        const afterSync = hasQuota ? quotaInfo.used + totalCount : 0;

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

        let bodyParts = [strings[0]];
        let idx = dynamicIdx;
        if (hasQuota) {
            bodyParts.push(strings[idx++]);
            if (afterSync > quotaInfo.limit) {
                bodyParts.push(strings[idx]);
            }
        }

        Notification.confirm(
            strings[1],
            bodyParts.join('<br><br>'),
            strings[2],
            strings[3],
            startBulkSync
        );
    };

    /**
     * Build the final result message with optional skipped user info.
     *
     * @param {string} baseKey Lang string key for the base message
     * @param {number} processedCount Number of users processed
     * @param {Array} skippedIds Array of skipped user IDs
     * @returns {Promise<string>} The composed message
     */
    const buildResultMessage = async(baseKey, processedCount, skippedIds) => {
        let message = await Str.get_string(baseKey, 'local_mc_plugin', processedCount);
        if (skippedIds.length > 0) {
            const displayIds = skippedIds.length > MAX_SKIPPED_DISPLAY
                ? skippedIds.slice(0, MAX_SKIPPED_DISPLAY).join(', ') + ', ...'
                : skippedIds.join(', ');
            const skippedMsg = await Str.get_string('bulk_sync_skipped', 'local_mc_plugin', {
                count: skippedIds.length,
                ids: displayIds,
            });
            message += skippedMsg;
        }
        return message;
    };

    /**
     * Start the bulk sync process.
     */
    const startBulkSync = async() => {
        if (isRunning) {
            return;
        }
        isRunning = true;
        cancelled = false;

        const btn = container.querySelector(SEL.startBtn);
        const spinner = container.querySelector(SEL.spinner);
        const cancelBtn = container.querySelector(SEL.cancelBtn);
        const progressContainer = container.querySelector(SEL.progress);
        const progressBar = container.querySelector(SEL.progressBar);
        const progressText = container.querySelector(SEL.progressText);
        const resultEl = container.querySelector(SEL.result);

        if (btn) {
            btn.disabled = true;
        }
        if (spinner) {
            spinner.classList.remove('d-none');
        }
        if (cancelBtn) {
            cancelBtn.classList.remove('d-none');
            // Use AbortController to cleanly manage the cancel listener.
            cancelController = new AbortController();
            cancelBtn.addEventListener('click', () => {
                cancelled = true;
                cancelBtn.disabled = true;
            }, {signal: cancelController.signal});
        }
        if (progressContainer) {
            progressContainer.classList.remove('d-none');
        }
        if (resultEl) {
            resultEl.textContent = '';
        }

        let offset = 0;
        let totalProcessed = 0;
        let allSkipped = [];

        try {
            while (true) { // eslint-disable-line no-constant-condition
                if (cancelled) {
                    const cancelMsg = await buildResultMessage('bulk_sync_cancelled', totalProcessed, allSkipped);
                    showResult(resultEl, false, cancelMsg);
                    break;
                }

                const result = await Repository.bulkSyncFire(config.syncUrl, config.sesskey, offset, BATCH_SIZE);

                if (!result.success) {
                    const errorMsg = await buildResultMessage('bulk_sync_error', totalProcessed, allSkipped);
                    showResult(resultEl, false, errorMsg);
                    break;
                }

                totalProcessed += result.processed;
                if (result.skipped && result.skipped.length > 0) {
                    allSkipped.push(...result.skipped);
                }
                offset += BATCH_SIZE;

                // Update progress bar.
                const percent = totalCount > 0 ? Math.min(Math.round((totalProcessed / totalCount) * 100), 100) : 0;
                if (progressBar) {
                    progressBar.style.width = percent + '%';
                    progressBar.textContent = percent + '%';
                    progressBar.setAttribute('aria-valuenow', percent);
                }
                if (progressText) {
                    const progressMsg = await Str.get_string(
                        'bulk_sync_progress', 'local_mc_plugin',
                        {processed: totalProcessed, total: totalCount}
                    );
                    progressText.textContent = progressMsg;
                }

                if (!result.has_more) {
                    const successMsg = await buildResultMessage('bulk_sync_success', totalProcessed, allSkipped);
                    showResult(resultEl, true, successMsg);
                    break;
                }
            }
        } catch (err) {
            const errorMsg = await buildResultMessage('bulk_sync_error', totalProcessed, allSkipped);
            showResult(resultEl, false, errorMsg);
        }

        // Clean up cancel listener and reset UI.
        if (cancelController) {
            cancelController.abort();
            cancelController = null;
        }
        if (spinner) {
            spinner.classList.add('d-none');
        }
        if (cancelBtn) {
            cancelBtn.classList.add('d-none');
            cancelBtn.disabled = false;
        }
        if (btn) {
            btn.disabled = false;
        }
        isRunning = false;
    };

    return {init};
});
