/**
 * Event selector module for the admin settings page.
 *
 * Handles event search, filtering, and bulk selection.
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
 * Get the hidden input element.
 *
 * @param {string} inputId The input element ID
 * @returns {HTMLElement|null}
 */
const getHiddenInput = (inputId) => document.getElementById(inputId);

/**
 * Update the hidden input value with selected events.
 *
 * @param {string} inputId The input element ID
 */
const updateValue = (inputId) => {
    const hiddenInput = getHiddenInput(inputId);
    const counter = document.querySelector(Selectors.events.counter(inputId));

    if (!hiddenInput || !counter) {
        return;
    }

    const selected = [];
    document.querySelectorAll(`${Selectors.events.checkbox}:checked`).forEach((cb) => {
        selected.push(cb.getAttribute('data-class'));
    });
    hiddenInput.value = selected.join(',');

    // Update counter with sync status
    if (syncedEvents.length > 0) {
        const toAdd = selected.filter((evt) => !syncedEvents.includes(evt)).length;
        const toRemove = syncedEvents.filter((evt) => !selected.includes(evt)).length;

        if (toAdd === 0 && toRemove === 0) {
            counter.innerHTML = strings.selectedCount.replace('{$a}', selected.length) +
                ` <span style="color:#155724;">• ${strings.allSynced}</span>`;
        } else {
            const changes = [];
            if (toAdd > 0) {
                changes.push(`${toAdd} ${strings.newEvents}`);
            }
            if (toRemove > 0) {
                changes.push(`${toRemove} ${strings.removed}`);
            }
            counter.innerHTML = strings.selectedCount.replace('{$a}', selected.length) +
                ` <span style="color:#856404;">• ${changes.join(', ')}</span>`;
        }
    } else {
        counter.textContent = strings.selectedCount.replace('{$a}', selected.length);
    }
};

/**
 * Filter events based on search term.
 *
 * @param {string} term Search term
 */
const filterEvents = (term) => {
    const lowerTerm = term.toLowerCase();

    document.querySelectorAll(Selectors.events.eventItem).forEach((item) => {
        const text = item.textContent.toLowerCase();
        if (text.includes(lowerTerm)) {
            item.classList.remove(Selectors.events.hiddenClass);
        } else {
            item.classList.add(Selectors.events.hiddenClass);
        }
    });

    // Hide empty categories
    document.querySelectorAll(Selectors.events.category).forEach((cat) => {
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
 *
 * @param {string} inputId The input element ID
 */
const selectVisible = (inputId) => {
    document.querySelectorAll(`${Selectors.events.eventItem}:not(.${Selectors.events.hiddenClass}) ${Selectors.events.checkbox}`)
        .forEach((cb) => {
            cb.checked = true;
        });
    updateValue(inputId);
};

/**
 * Deselect all visible checkboxes.
 *
 * @param {string} inputId The input element ID
 */
const deselectVisible = (inputId) => {
    document.querySelectorAll(`${Selectors.events.eventItem}:not(.${Selectors.events.hiddenClass}) ${Selectors.events.checkbox}`)
        .forEach((cb) => {
            cb.checked = false;
        });
    updateValue(inputId);
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
 * @param {string} inputId The input element ID
 */
export const refreshCounter = (inputId) => {
    updateValue(inputId);
};

/**
 * Initialize the event selector.
 *
 * @param {string} inputId The hidden input element ID
 */
export const init = async(inputId) => {
    await loadStrings();

    // Initial counter update
    updateValue(inputId);

    // Checkbox change handler
    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('event-checkbox')) {
            updateValue(inputId);
        }
    });

    // Category collapse/expand
    document.querySelectorAll(Selectors.events.categoryTitle).forEach((title) => {
        title.addEventListener('click', () => {
            const events = title.nextElementSibling;
            events.style.display = events.style.display === 'none' ? 'block' : 'none';
        });
    });

    // Search input
    const searchInput = document.querySelector(Selectors.events.searchInput(inputId));
    if (searchInput) {
        searchInput.addEventListener('keyup', (e) => {
            filterEvents(e.target.value);
        });
    }

    // Select/deselect visible buttons
    const selectBtn = document.querySelector(Selectors.events.selectVisibleBtn(inputId));
    if (selectBtn) {
        selectBtn.addEventListener('click', () => selectVisible(inputId));
    }

    const deselectBtn = document.querySelector(Selectors.events.deselectVisibleBtn(inputId));
    if (deselectBtn) {
        deselectBtn.addEventListener('click', () => deselectVisible(inputId));
    }
};
