/**
 * CSS Selectors for the admin settings page.
 *
 * Uses data-* attribute selectors for template-rendered elements.
 * Legacy ID-based selectors are kept for backward compatibility.
 *
 * @module     local_mc_plugin/local/admin/selectors
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default {
    // Connect button section (data-* attributes from connect_button.mustache)
    connect: {
        // Container
        container: '.local_mc_plugin_connect_button',
        // Button and its parts
        button: '[data-action="connect"]',
        buttonText: '[data-region="button-text"]',
        buttonSpinner: '[data-region="button-spinner"]',
        // Status display
        statusDiv: '[data-region="connect-status"]',
        statusIcon: '[data-region="status-icon"]',
        statusText: '[data-region="status-text"]',
        // Legacy ID-based selectors (backward compatibility)
        legacyButton: '#mc-connect-btn',
        legacyButtonText: '#mc-connect-btn-text',
        legacyButtonSpinner: '#mc-connect-btn-spinner',
        legacyStatusDiv: '#mc-connect-status',
        legacyStatusIcon: '#mc-connect-status-icon',
        legacyStatusText: '#mc-connect-status-text',
    },

    // Connection status section (data-* attributes from connection_status.mustache)
    status: {
        // Container with config data
        container: '[data-region="connection-status"]',
        // Content area for dynamic updates
        content: '[data-region="status-content"]',
        // Dynamic content regions
        spinner: '[data-region="status-spinner"]',
        text: '[data-region="status-text"]',
        syncStatus: '[data-region="sync-status"]',
        // Legacy ID-based selectors (backward compatibility)
        legacyContainer: '#mc-connection-status',
        legacyDot: '#mc-status-dot',
        legacyText: '#mc-status-text',
        legacySiteName: '#mc-site-name',
        legacySyncStatus: '#mc-sync-status',
        legacyTestResult: '#mc-test-result',
    },

    // Event selector section (data-* attributes from event_selector.mustache)
    events: {
        // Container
        container: '[data-region="event-selector"]',
        // Form elements
        hiddenInput: '[data-region="events-input"]',
        searchInput: '[data-region="search-input"]',
        counter: '[data-region="selected-count"]',
        // Buttons
        selectVisibleBtn: '[data-action="select-visible"]',
        deselectVisibleBtn: '[data-action="deselect-visible"]',
        // Event items
        checkbox: '[data-action="toggle-event"]',
        eventItem: '.mc-event-item',
        category: '.mc-category',
        categoryTitle: '.mc-category-title',
        categoryRegion: '[data-region="event-category"]',
        // CSS class for hiding filtered items
        hiddenClass: 'mc-hidden',
        // Legacy function-based selectors (backward compatibility)
        legacySearchInput: (id) => `#${id}_search`,
        legacyCounter: (id) => `#${id}_counter`,
        legacySelectVisibleBtn: (id) => `#${id}_select_visible`,
        legacyDeselectVisibleBtn: (id) => `#${id}_deselect_visible`,
        legacyCheckbox: '.event-checkbox',
    },

    // Action buttons section (data-* attributes from action_buttons.mustache)
    actions: {
        // Container with config data
        container: '[data-region="action-buttons"]',
        // Button and its parts
        primaryBtn: '[data-action="save-sync"]',
        btnText: '[data-region="button-text"]',
        btnSpinner: '[data-region="button-spinner"]',
        // Result message container
        resultDiv: '[data-region="action-result"]',
        // Legacy ID-based selectors (backward compatibility)
        legacyPrimaryBtn: '#mc-primary-btn',
        legacyBtnText: '#mc-btn-text',
        legacyBtnSpinner: '#mc-btn-spinner',
        legacyResultDiv: '#mc-action-result',
    },

    // Form inputs (standard Moodle admin setting names)
    inputs: {
        monitoredEvents: 'input[name="s_local_mc_plugin_monitored_events"]',
        siteKey: 'input[name="s_local_mc_plugin_site_key"]',
        siteSecret: 'input[name="s_local_mc_plugin_site_secret"]',
        debugMode: 'input[name="s_local_mc_plugin_debug_mode"]',
    },
};
