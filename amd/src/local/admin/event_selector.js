/**
 * Event selector module for the admin settings page.
 *
 * Handles event search, filtering, and bulk selection.
 * Uses data-* attributes from event_selector.mustache template.
 *
 * @module     local_mc_plugin/local/admin/event_selector
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Selectors from './selectors';
import {get_strings as getStrings} from 'core/str';

/** @type {Array} Currently synced events from MoodleConnect */
let syncedEvents = [];

/** @type {Object} Language strings */
let strings = {};

/** @type {HTMLElement|null} Event selector container */
let container = null;

/** @type {HTMLElement|null} Hidden input element */
let hiddenInput = null;

/** @type {HTMLElement|null} Counter element */
let counterEl = null;

/**
 * Load language strings.
 *
 * @returns {Promise<void>}
 */
const loadStrings = async() => {
    const results = await getStrings([
        {key: 'event_selected_count', component: 'local_mc_plugin'},
        {key: 'event_all_synced', component: 'local_mc_plugin'},
        {key: 'event_new', component: 'local_mc_plugin'},
        {key: 'event_removed', component: 'local_mc_plugin'},
    ]);

    strings = {
        selectedCount: results[0],
        allSynced: results[1],
        newEvents: results[2],
        removed: results[3],
    };
};

/**
 * Update the hidden input value with selected events.
 */
const updateValue = () => {
    if (!hiddenInput || !counterEl) {
        return;
    }

    const selected = [];
    // Use data-action selector for checkboxes from template
    const checkboxes = container ?
        container.querySelectorAll(`${Selectors.events.checkbox}:checked`) :
        document.querySelectorAll(`${Selectors.events.checkbox}:checked, ${Selectors.events.legacyCheckbox}:checked`);

    checkboxes.forEach((cb) => {
        // Get class from parent mc-event-item's data-class attribute
        const eventItem = cb.closest(Selectors.events.eventItem);
        if (eventItem && eventItem.dataset.class) {
            selected.push(eventItem.dataset.class);
        }
    });

    hiddenInput.value = selected.join(',');

    // Update counter with sync status
    if (syncedEvents.length > 0) {
        const toAdd = selected.filter((evt) => !syncedEvents.includes(evt)).length;
        const toRemove = syncedEvents.filter((evt) => !selected.includes(evt)).length;

        if (toAdd === 0 && toRemove === 0) {
            counterEl.innerHTML = strings.selectedCount.replace('{$a}', selected.length) +
                ` <span class="text-success">• ${strings.allSynced}</span>`;
        } else {
            const changes = [];
            if (toAdd > 0) {
                changes.push(`${toAdd} ${strings.newEvents}`);
            }
            if (toRemove > 0) {
                changes.push(`${toRemove} ${strings.removed}`);
            }
            counterEl.innerHTML = strings.selectedCount.replace('{$a}', selected.length) +
                ` <span class="text-warning">• ${changes.join(', ')}</span>`;
        }
    } else {
        counterEl.textContent = strings.selectedCount.replace('{$a}', selected.length);
    }
};


/**
 * Filter events based on search term.
 *
 * @param {string} term Search term
 */
const filterEvents = (term) => {
    const lowerTerm = term.toLowerCase();
    const eventItems = container ?
        container.querySelectorAll(Selectors.events.eventItem) :
        document.querySelectorAll(Selectors.events.eventItem);

    eventItems.forEach((item) => {
        const text = item.textContent.toLowerCase();
        if (text.includes(lowerTerm)) {
            item.classList.remove(Selectors.events.hiddenClass);
        } else {
            item.classList.add(Selectors.events.hiddenClass);
        }
    });

    // Hide empty categories
    const categories = container ?
        container.querySelectorAll(Selectors.events.category) :
        document.querySelectorAll(Selectors.events.category);

    categories.forEach((cat) => {
        const visible = cat.querySelectorAll(`${Selectors.events.eventItem}:not(.${Selectors.events.hiddenClass})`).length;
        if (visible === 0) {
            cat.classList.add(Selectors.events.hiddenClass);
        } else {
            cat.classList.remove(Selectors.events.hiddenClass);
        }
    });
};

/**
 * Select all visible checkboxes.
 */
const selectVisible = () => {
    const selector = `${Selectors.events.eventItem}:not(.${Selectors.events.hiddenClass}) ${Selectors.events.checkbox}`;
    const checkboxes = container ?
        container.querySelectorAll(selector) :
        document.querySelectorAll(selector);

    checkboxes.forEach((cb) => {
        cb.checked = true;
    });
    updateValue();
};

/**
 * Deselect all visible checkboxes.
 */
const deselectVisible = () => {
    const selector = `${Selectors.events.eventItem}:not(.${Selectors.events.hiddenClass}) ${Selectors.events.checkbox}`;
    const checkboxes = container ?
        container.querySelectorAll(selector) :
        document.querySelectorAll(selector);

    checkboxes.forEach((cb) => {
        cb.checked = false;
    });
    updateValue();
};

/**
 * Set the synced events array.
 *
 * @param {Array} events Array of synced event class names
 */
export const setSyncedEvents = (events) => {
    syncedEvents = events || [];
};

/**
 * Trigger a counter update (called from other modules).
 *
 * @param {string} [inputId] The input element ID (for backward compatibility, ignored)
 */
export const refreshCounter = (inputId) => { // eslint-disable-line no-unused-vars
    updateValue();
};

/**
 * Initialize the event selector.
 *
 * Finds elements using data-* attribute selectors from the template.
 * Falls back to legacy ID-based selectors for backward compatibility.
 *
 * @param {string} [inputId] The hidden input element ID (for backward compatibility)
 */
export const init = async(inputId = null) => {
    await loadStrings();

    // Find the event selector container using data-region attribute
    container = document.querySelector(Selectors.events.container);

    if (container) {
        // Find elements within container using data-* selectors
        hiddenInput = container.querySelector(Selectors.events.hiddenInput);
        counterEl = container.querySelector(Selectors.events.counter);

        // Search input handler
        const searchInput = container.querySelector(Selectors.events.searchInput);
        if (searchInput) {
            searchInput.addEventListener('keyup', (e) => {
                filterEvents(e.target.value);
            });
        }

        // Select/deselect visible buttons
        const selectBtn = container.querySelector(Selectors.events.selectVisibleBtn);
        if (selectBtn) {
            selectBtn.addEventListener('click', selectVisible);
        }

        const deselectBtn = container.querySelector(Selectors.events.deselectVisibleBtn);
        if (deselectBtn) {
            deselectBtn.addEventListener('click', deselectVisible);
        }

        // Category collapse/expand
        container.querySelectorAll(Selectors.events.categoryTitle).forEach((title) => {
            title.style.cursor = 'pointer';
            title.addEventListener('click', () => {
                const events = title.nextElementSibling;
                if (events) {
                    events.style.display = events.style.display === 'none' ? 'block' : 'none';
                }
            });
        });
    } else if (inputId) {
        // Fallback to legacy ID-based selectors
        hiddenInput = document.getElementById(inputId);
        counterEl = document.querySelector(Selectors.events.legacyCounter(inputId));

        // Search input handler (legacy)
        const searchInput = document.querySelector(Selectors.events.legacySearchInput(inputId));
        if (searchInput) {
            searchInput.addEventListener('keyup', (e) => {
                filterEvents(e.target.value);
            });
        }

        // Select/deselect visible buttons (legacy)
        const selectBtn = document.querySelector(Selectors.events.legacySelectVisibleBtn(inputId));
        if (selectBtn) {
            selectBtn.addEventListener('click', selectVisible);
        }

        const deselectBtn = document.querySelector(Selectors.events.legacyDeselectVisibleBtn(inputId));
        if (deselectBtn) {
            deselectBtn.addEventListener('click', deselectVisible);
        }

        // Category collapse/expand (legacy)
        document.querySelectorAll(Selectors.events.categoryTitle).forEach((title) => {
            title.style.cursor = 'pointer';
            title.addEventListener('click', () => {
                const events = title.nextElementSibling;
                if (events) {
                    events.style.display = events.style.display === 'none' ? 'block' : 'none';
                }
            });
        });
    }

    // Initial counter update
    updateValue();

    // Checkbox change handler (delegated to document for dynamic content)
    document.addEventListener('change', (e) => {
        // Check for both template and legacy checkbox selectors
        if (e.target.matches(Selectors.events.checkbox) ||
            e.target.matches(Selectors.events.legacyCheckbox)) {
            updateValue();
        }
    });
};
