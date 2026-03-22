// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

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
     */
    const init = async function() {
        if (container) {
            return; // Already initialized - prevent duplicate listeners on re-init.
        }

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
            const result = await Repository.bulkSyncCount();

            if (!result.success) {
                showStatus(statusEl, result.message, 'text-danger');
                return;
            }

            totalCount = result.count;
            quotaInfo = result.quota_limit > 0 ? {used: result.quota_used, limit: result.quota_limit} : null;

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
     * Get references to all UI elements used during sync.
     *
     * @returns {Object} UI element references
     */
    const getSyncElements = () => ({
        btn: container.querySelector(SEL.startBtn),
        spinner: container.querySelector(SEL.spinner),
        cancelBtn: container.querySelector(SEL.cancelBtn),
        progressContainer: container.querySelector(SEL.progress),
        progressBar: container.querySelector(SEL.progressBar),
        progressText: container.querySelector(SEL.progressText),
        resultEl: container.querySelector(SEL.result),
    });

    /**
     * Set up UI for sync start: disable button, show spinner/progress/cancel.
     *
     * @param {Object} els UI element references from getSyncElements
     */
    const setupSyncUI = (els) => {
        if (els.btn) {
            els.btn.disabled = true;
        }
        if (els.spinner) {
            els.spinner.classList.remove('d-none');
        }
        if (els.cancelBtn) {
            els.cancelBtn.classList.remove('d-none');
            cancelController = new AbortController();
            els.cancelBtn.addEventListener('click', () => {
                cancelled = true;
                els.cancelBtn.disabled = true;
            }, {signal: cancelController.signal});
        }
        if (els.progressContainer) {
            els.progressContainer.classList.remove('d-none');
        }
        if (els.resultEl) {
            els.resultEl.textContent = '';
        }
    };

    /**
     * Reset UI after sync completes: hide spinner/cancel, re-enable button.
     *
     * @param {Object} els UI element references from getSyncElements
     */
    const resetSyncUI = (els) => {
        if (cancelController) {
            cancelController.abort();
            cancelController = null;
        }
        if (els.spinner) {
            els.spinner.classList.add('d-none');
        }
        if (els.cancelBtn) {
            els.cancelBtn.classList.add('d-none');
            els.cancelBtn.disabled = false;
        }
        if (els.btn) {
            els.btn.disabled = false;
        }
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

        const els = getSyncElements();
        setupSyncUI(els);

        let offset = 0;
        let totalProcessed = 0;
        let allSkipped = [];

        try {
            while (true) { // eslint-disable-line no-constant-condition
                if (cancelled) {
                    const cancelMsg = await buildResultMessage('bulk_sync_cancelled', totalProcessed, allSkipped);
                    showResult(els.resultEl, false, cancelMsg);
                    break;
                }

                const result = await Repository.bulkSyncFire(offset, BATCH_SIZE);

                if (!result.success) {
                    const errorMsg = await buildResultMessage('bulk_sync_error', totalProcessed, allSkipped);
                    showResult(els.resultEl, false, errorMsg);
                    break;
                }

                totalProcessed += result.processed;
                if (result.skipped && result.skipped.length > 0) {
                    allSkipped.push(...result.skipped);
                }
                offset += BATCH_SIZE;

                // Update progress bar.
                const percent = totalCount > 0 ? Math.min(Math.round((totalProcessed / totalCount) * 100), 100) : 0;
                if (els.progressBar) {
                    els.progressBar.style.width = percent + '%';
                    els.progressBar.textContent = percent + '%';
                    els.progressBar.setAttribute('aria-valuenow', percent);
                }
                if (els.progressText) {
                    const progressMsg = await Str.get_string(
                        'bulk_sync_progress', 'local_mc_plugin',
                        {processed: totalProcessed, total: totalCount}
                    );
                    els.progressText.textContent = progressMsg;
                }

                if (!result.has_more) {
                    const successMsg = await buildResultMessage('bulk_sync_success', totalProcessed, allSkipped);
                    showResult(els.resultEl, true, successMsg);
                    break;
                }
            }
        } catch (err) {
            const errorMsg = await buildResultMessage('bulk_sync_error', totalProcessed, allSkipped);
            showResult(els.resultEl, false, errorMsg);
        }

        resetSyncUI(els);
        isRunning = false;
    };

    return {init};
});
