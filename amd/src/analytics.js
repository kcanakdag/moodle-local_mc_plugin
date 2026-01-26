/**
 * Analytics dashboard module.
 *
 * Uses Mustache templates rendered server-side for consistent display.
 *
 * @module     local_mc_plugin/analytics
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    "use strict";

    var currentDays = 30;
    var currentCategory = 'all';
    var container = null;

    /**
     * Load analytics data via AJAX and update content.
     *
     * @param {number} days Number of days
     * @param {string} category Event category
     */
    var loadData = function(days, category) {
        currentDays = days;
        currentCategory = category;

        // Show loading state.
        var content = container.querySelector('.mc-analytics-content');
        if (content) {
            content.style.opacity = '0.5';
            content.style.pointerEvents = 'none';
        }

        Ajax.call([{
            methodname: 'local_mc_plugin_get_analytics',
            args: {days: days, category: category}
        }])[0].then(function(data) {
            // Replace content with server-rendered HTML.
            if (content) {
                content.innerHTML = data.html;
                content.style.opacity = '1';
                content.style.pointerEvents = '';
            }
            updateActiveStates();
            return null;
        }).catch(function(error) {
            Notification.exception(error);
            if (content) {
                content.style.opacity = '1';
                content.style.pointerEvents = '';
            }
        });
    };

    /**
     * Update active states on filter buttons.
     */
    var updateActiveStates = function() {
        // Update day buttons.
        container.querySelectorAll('[data-days]').forEach(function(btn) {
            btn.classList.toggle('active', parseInt(btn.dataset.days) === currentDays);
        });

        // Update category select.
        var select = container.querySelector('[data-category-select]');
        if (select) {
            select.value = currentCategory;
        }
    };

    /**
     * Initialize the analytics module.
     *
     * @param {Object} cfg Configuration
     * @param {number} cfg.days Initial days
     * @param {string} cfg.category Initial category
     */
    var init = function(cfg) {
        container = document.getElementById('mc-analytics-container');
        if (!container) {
            return;
        }

        currentDays = cfg.days || 30;
        currentCategory = cfg.category || 'all';

        // Day filter buttons.
        container.querySelectorAll('[data-days]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var days = parseInt(this.dataset.days);
                if (days !== currentDays) {
                    loadData(days, currentCategory);
                }
            });
        });

        // Category select.
        var select = container.querySelector('[data-category-select]');
        if (select) {
            select.addEventListener('change', function() {
                var category = this.value;
                if (category !== currentCategory) {
                    loadData(currentDays, category);
                }
            });
        }

        updateActiveStates();
    };

    return {
        init: init
    };
});
