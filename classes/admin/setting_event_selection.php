<?php
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
 * Custom admin setting for event selection with search and filtering.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Canakdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

class setting_event_selection extends \admin_setting_configtext {

    public function __construct($name, $visiblename, $description, $defaultsetting) {
        parent::__construct($name, $visiblename, $description, $defaultsetting, PARAM_RAW);
    }

    public function output_html($data, $query = '') {
        global $OUTPUT, $PAGE;

        $selected_list = array_map('trim', explode(',', $data));
        $selected_map = array_flip($selected_list); 

        $discovery = new \local_mc_plugin\local\event_discovery();
        try {
            $events = $discovery->get_all_events();
        } catch (\Exception $e) {
            return $OUTPUT->notification('Error loading events: ' . $e->getMessage(), 'notifyproblem');
        }

        $grouped = [];
        foreach ($events as $event) {
            $cat = $event['component'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $event;
        }
        ksort($grouped);

        $id = $this->get_id();

        $html = "
        <style>
            .mc-event-selector {
                border: 1px solid #ccc;
                border-radius: 4px;
                max-height: 500px;
                overflow-y: auto;
                padding: 10px;
                background: #fff;
                margin-top: 10px;
            }
            .mc-controls {
                display: flex;
                gap: 10px;
                align-items: center;
            }
            .mc-event-search {
                flex-grow: 1;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .mc-btn-small {
                padding: 5px 10px;
                background: #f0f0f0;
                border: 1px solid #ccc;
                border-radius: 4px;
                cursor: pointer;
                font-size: 0.9em;
            }
            .mc-btn-small:hover {
                background: #e0e0e0;
            }
            .mc-category {
                margin-bottom: 15px;
            }
            .mc-category-title {
                font-weight: bold;
                background: #f5f5f5;
                padding: 5px 10px;
                cursor: pointer;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-radius: 4px;
                user-select: none;
            }
            .mc-category-events {
                padding-left: 10px;
                margin-top: 5px;
            }
            .mc-event-item {
                display: flex;
                align-items: center;
                padding: 2px 0;
            }
            .mc-event-item label {
                margin-left: 8px;
                margin-bottom: 0;
                cursor: pointer;
                font-weight: normal;
            }
            .mc-event-class {
                color: #888;
                font-size: 0.85em;
                margin-left: 5px;
            }
            .mc-hidden {
                display: none !important;
            }
        </style>
        ";

        $html .= '<input type="hidden" name="' . $this->get_full_name() . '" id="' . $id . '" value="' . s($data) . '">';

        $html .= '<div class="mc-event-selector-wrapper">';
        
        $html .= '<div class="mc-controls">';
        $html .= '<input type="text" id="' . $id . '_search" class="mc-event-search" placeholder="Search events...">';
        $html .= '<span id="' . $id . '_counter" style="font-size:0.9em;color:#666;min-width:100px;">0 selected</span>';
        $html .= '<button type="button" id="' . $id . '_select_visible" class="mc-btn-small">Select Visible</button>';
        $html .= '<button type="button" id="' . $id . '_deselect_visible" class="mc-btn-small">Deselect Visible</button>';
        $html .= '</div>';

        $html .= '<div class="mc-event-selector">';

        foreach ($grouped as $category => $cat_events) {
            $cat_label = ($category === 'core') ? 'Core' : str_replace('_', ' ', $category);
            $cat_label = ucwords($cat_label);

            $html .= '<div class="mc-category">';
            $html .= '<div class="mc-category-title">' . $cat_label . ' <small>(' . count($cat_events) . ')</small></div>';
            $html .= '<div class="mc-category-events">';

            foreach ($cat_events as $event) {
                $checked = isset($selected_map[$event['class']]) ? 'checked' : '';
                $escaped_class = htmlspecialchars($event['class'], ENT_QUOTES, 'UTF-8');
                
                $html .= '<div class="mc-event-item">';
                $html .= '<input type="checkbox" class="event-checkbox" data-class="' . $escaped_class . '" ' . $checked . '>';
                $html .= '<label>' . s($event['name']);
                $html .= '<span class="mc-event-class">' . s($event['class']) . '</span>';
                $html .= '</label>';
                $html .= '</div>';
            }

            $html .= '</div></div>';
        }

        $html .= '</div></div>';

        $html .= "
        <script>
        (function() {
            var inputId = '" . $id . "';
            var hiddenInput = document.getElementById(inputId);
            var searchInput = document.getElementById(inputId + '_search');
            var selectBtn = document.getElementById(inputId + '_select_visible');
            var deselectBtn = document.getElementById(inputId + '_deselect_visible');
            var counter = document.getElementById(inputId + '_counter');
            
            function updateValue() {
                var selected = [];
                document.querySelectorAll('.event-checkbox:checked').forEach(function(cb) {
                    selected.push(cb.getAttribute('data-class'));
                });
                hiddenInput.value = selected.join(',');
                
                var syncedEvents = window.mcSyncedEvents || [];
                
                if (syncedEvents.length > 0) {
                    var toAdd = 0;
                    selected.forEach(function(evt) {
                        if (syncedEvents.indexOf(evt) < 0) {
                            toAdd++;
                        }
                    });
                    
                    var toRemove = 0;
                    syncedEvents.forEach(function(evt) {
                        if (selected.indexOf(evt) < 0) {
                            toRemove++;
                        }
                    });
                    
                    if (toAdd === 0 && toRemove === 0) {
                        counter.innerHTML = selected.length + ' selected <span style=\"color:#155724;\">• all synced</span>';
                    } else {
                        var changes = [];
                        if (toAdd > 0) changes.push(toAdd + ' new');
                        if (toRemove > 0) changes.push(toRemove + ' removed');
                        counter.innerHTML = selected.length + ' selected <span style=\"color:#856404;\">• ' + changes.join(', ') + '</span>';
                    }
                } else {
                    counter.textContent = selected.length + ' selected';
                }
            }
            
            window.mcUpdateEventCounter = updateValue;
            updateValue();

            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('event-checkbox')) {
                    updateValue();
                }
            });

            document.querySelectorAll('.mc-category-title').forEach(function(title) {
                title.addEventListener('click', function() {
                    var events = this.nextElementSibling;
                    events.style.display = events.style.display === 'none' ? 'block' : 'none';
                });
            });

            searchInput.addEventListener('keyup', function() {
                var term = this.value.toLowerCase();
                
                document.querySelectorAll('.mc-event-item').forEach(function(item) {
                    var text = item.textContent.toLowerCase();
                    if (text.indexOf(term) > -1) {
                        item.classList.remove('mc-hidden');
                    } else {
                        item.classList.add('mc-hidden');
                    }
                });

                document.querySelectorAll('.mc-category').forEach(function(cat) {
                    var visible = cat.querySelectorAll('.mc-event-item:not(.mc-hidden)').length;
                    if (visible === 0) {
                        cat.classList.add('mc-hidden');
                    } else {
                        cat.classList.remove('mc-hidden');
                    }
                });
            });

            selectBtn.addEventListener('click', function() {
                document.querySelectorAll('.mc-event-item:not(.mc-hidden) .event-checkbox').forEach(function(cb) {
                    cb.checked = true;
                });
                updateValue();
            });

            deselectBtn.addEventListener('click', function() {
                document.querySelectorAll('.mc-event-item:not(.mc-hidden) .event-checkbox').forEach(function(cb) {
                    cb.checked = false;
                });
                updateValue();
            });
        })();
        </script>
        ";

        return format_admin_setting($this, $this->visiblename, $html, $this->description, true, '', '', $query);
    }
}
