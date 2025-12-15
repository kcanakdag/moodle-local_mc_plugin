/**
 * Main entry point for the admin settings page.
 *
 * This module coordinates all admin page functionality.
 *
 * @module     local_mc_plugin/admin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as EventSelector from './local/admin/event_selector';
import * as ConnectionStatus from './local/admin/connection_status';
import * as ActionButtons from './local/admin/action_buttons';
import * as Connect from './connect';

/**
 * Hide Moodle's default save button since we use custom AJAX saving.
 * Uses a theme-agnostic approach by finding submit buttons in the admin form.
 */
const hideDefaultSaveButton = () => {
    const form = document.getElementById('adminsettings');
    if (!form) {
        return;
    }

    // Find all submit buttons in the form
    const submitButtons = form.querySelectorAll('button[type="submit"]');
    submitButtons.forEach(btn => {
        // Skip our custom buttons
        if (btn.closest('.local_mc_plugin_action_buttons') || btn.closest('[data-region="action-buttons"]')) {
            return;
        }

        // Hide the button's container (try .row first, then parent div)
        const container = btn.closest('.row') || btn.parentElement;
        if (container) {
            container.style.display = 'none';
        }
    });
};

/**
 * Initialize the event selector component.
 *
 * @param {Object} cfg Configuration object
 * @param {string} cfg.inputId The hidden input element ID
 */
export const initEventSelector = (cfg) => {
    EventSelector.init(cfg.inputId);
};

/**
 * Initialize the connection status component.
 *
 * @param {Object} cfg Configuration object
 * @param {string} cfg.syncUrl URL to sync_schema.php
 * @param {string} cfg.sesskey Moodle session key
 * @param {string} cfg.eventInputId Event selector input ID for counter refresh
 */
export const initConnectionStatus = (cfg) => {
    // Hide default save button when connection status initializes
    hideDefaultSaveButton();
    ConnectionStatus.init(cfg);
};

/**
 * Initialize the action buttons component.
 *
 * @param {Object} cfg Configuration object
 * @param {string} cfg.syncUrl URL to sync_schema.php
 * @param {string} cfg.ajaxSaveUrl URL to ajax_save.php
 * @param {string} cfg.sesskey Moodle session key
 */
export const initActionButtons = (cfg) => {
    ActionButtons.init(cfg);
};

/**
 * Initialize the connect button component.
 *
 * @param {Object} cfg Configuration object
 * @param {string} cfg.connectUrl URL to connect.php
 * @param {string} cfg.saveUrl URL to ajax_save.php
 * @param {string} cfg.apiUrl MoodleConnect API URL
 * @param {string} cfg.frontendUrl MoodleConnect frontend URL
 * @param {string} cfg.sesskey Moodle session key
 * @param {boolean} cfg.isConnected Whether already connected
 */
export const initConnect = (cfg) => {
    Connect.init(cfg);
};
