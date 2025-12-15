/**
 * CSS Selectors for the admin settings page.
 *
 * @module     local_mc_plugin/local/admin/selectors
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default {
    // Connection section
    connect: {
        button: '#mc-connect-btn',
        buttonText: '#mc-connect-btn-text',
        buttonSpinner: '#mc-connect-btn-spinner',
        statusDiv: '#mc-connect-status',
        statusIcon: '#mc-connect-status-icon',
        statusText: '#mc-connect-status-text',
    },

    // Connection status section
    status: {
        container: '#mc-connection-status',
        dot: '#mc-status-dot',
        text: '#mc-status-text',
        siteName: '#mc-site-name',
        syncStatus: '#mc-sync-status',
        testResult: '#mc-test-result',
    },

    // Event selector section
    events: {
        searchInput: (id) => `#${id}_search`,
        counter: (id) => `#${id}_counter`,
        selectVisibleBtn: (id) => `#${id}_select_visible`,
        deselectVisibleBtn: (id) => `#${id}_deselect_visible`,
        checkbox: '.event-checkbox',
        eventItem: '.mc-event-item',
        category: '.mc-category',
        categoryTitle: '.mc-category-title',
        hiddenClass: 'mc-hidden',
    },

    // Action buttons section
    actions: {
        primaryBtn: '#mc-primary-btn',
        btnText: '#mc-btn-text',
        btnSpinner: '#mc-btn-spinner',
        resultDiv: '#mc-action-result',
    },

    // Form inputs
    inputs: {
        monitoredEvents: 'input[name="s_local_mc_plugin_monitored_events"]',
        siteKey: 'input[name="s_local_mc_plugin_site_key"]',
        siteSecret: 'input[name="s_local_mc_plugin_site_secret"]',
        debugMode: 'input[name="s_local_mc_plugin_debug_mode"]',
    },
};
