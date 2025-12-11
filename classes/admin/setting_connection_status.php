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
 * Custom admin setting that displays connection and sync status.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Custom admin setting that displays connection and sync status.
 */
class setting_connection_status extends \admin_setting {
    /** @var bool Whether the site is currently connected */
    private $isconnected;

    /**
     * Constructor.
     *
     * @param string $name Unique setting name
     * @param bool $isconnected Whether the site is currently connected
     */
    public function __construct($name, $isconnected) {
        $this->isconnected = $isconnected;
        parent::__construct($name, get_string('connection_status', 'local_mc_plugin'), '', '');
    }

    /**
     * Returns current value of this setting.
     *
     * @return bool Always returns true (this is a status display, not a stored value)
     */
    public function get_setting() {
        return true;
    }

    /**
     * This setting is not stored, so write does nothing.
     *
     * @param mixed $data Unused
     * @return string Empty string (no error)
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * Returns the HTML for this setting.
     *
     * @param mixed $data Current value
     * @param string $query Search query
     * @return string HTML output
     */
    public function output_html($data, $query = '') {
        $syncurl = (new \moodle_url('/local/mc_plugin/sync_schema.php'))->out(false);

        // Always show the dynamic status UI.
        $html = '<div id="mc-connection-status">';
        $html .= '<div id="mc-status-display">';
        $html .= '<span id="mc-status-dot" style="color: #6c757d; margin-right: 6px;">●</span>';
        $html .= '<span id="mc-status-text" style="color: #6c757d; font-weight: 500;">Not configured</span>';
        $html .= '<span id="mc-site-name" style="margin-left: 8px; color: #666;"></span>';
        $html .= '<span id="mc-sync-status" style="margin-left: 12px; font-size: 0.9em; color: #666;"></span>';
        $html .= '</div>';
        $html .= '<span id="mc-test-result" style="margin-left: 10px; font-size: 0.85em;"></span>';
        $html .= '</div>';

        // Always include the JavaScript.
        $html .= '
            <script>
            (function() {
                var testResult = document.getElementById("mc-test-result");
                var statusText = document.getElementById("mc-status-text");
                var siteName = document.getElementById("mc-site-name");
                var statusDot = document.getElementById("mc-status-dot");
                var syncStatus = document.getElementById("mc-sync-status");
                var syncUrl = "' . $syncurl . '";

                function getSelectedEventCount() {
                    var eventsInput = document.querySelector("input[name=\"s_local_mc_plugin_monitored_events\"]");
                    if (!eventsInput || !eventsInput.value) return 0;
                    return eventsInput.value.split(",").filter(function(e) { return e.trim() !== ""; }).length;
                }

                function getSelectedEvents() {
                    var eventsInput = document.querySelector("input[name=\"s_local_mc_plugin_monitored_events\"]");
                    if (!eventsInput || !eventsInput.value) return [];
                    return eventsInput.value.split(",").map(function(e) { return e.trim(); }).filter(function(e) {
                        return e !== "";
                    });
                }

                function updateStatus(connected, siteName_val, syncedCount, syncedEvents, message) {
                    window.mcSyncedEvents = syncedEvents || [];

                    if (connected) {
                        statusDot.style.color = "#28a745";
                        statusText.style.color = "#155724";
                        statusText.textContent = "Connected";
                        if (siteName_val) {
                            siteName.textContent = "(" + siteName_val + ")";
                        }
                        testResult.innerHTML = "";

                        var selectedCount = getSelectedEventCount();
                        var selectedEvents = getSelectedEvents();

                        if (syncedCount === 0) {
                            syncStatus.innerHTML = "<span style=\"color: #856404;\">• Events not synced yet</span>";
                        } else if (syncedCount === selectedCount && syncedEvents) {
                            var allMatch = selectedEvents.every(function(e) { return syncedEvents.indexOf(e) >= 0; });
                            if (allMatch) {
                                syncStatus.innerHTML = "<span style=\"color: #155724;\">• " + syncedCount +
                                    " events synced</span>";
                            } else {
                                syncStatus.innerHTML = "<span style=\"color: #856404;\">• Events changed, " +
                                    "click Save & Sync</span>";
                            }
                        } else {
                            var diff = selectedCount - syncedCount;
                            if (diff > 0) {
                                syncStatus.innerHTML = "<span style=\"color: #856404;\">• " + diff +
                                    " new event(s) to sync</span>";
                            } else {
                                syncStatus.innerHTML = "<span style=\"color: #856404;\">• Events changed, " +
                                    "click Save & Sync</span>";
                            }
                        }

                        if (typeof window.mcUpdateEventCounter === "function") {
                            window.mcUpdateEventCounter();
                        }
                    } else {
                        statusDot.style.color = "#dc3545";
                        statusText.style.color = "#721c24";
                        statusText.textContent = "Not connected";
                        siteName.textContent = "";
                        syncStatus.textContent = "";
                        testResult.innerHTML = "<span style=\"color: #dc3545;\">" +
                            (message || "Connection failed") + "</span>";
                    }
                }



                function testConnection() {
                    statusDot.style.color = "#6c757d";
                    statusText.style.color = "#6c757d";
                    statusText.textContent = "Checking...";
                    syncStatus.textContent = "";
                    testResult.innerHTML = "";

                    fetch(syncUrl + "?action=status", { method: "GET" })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.connected) {
                            updateStatus(true, data.site_name, data.synced_event_count || 0, data.synced_events || []);
                        } else if (data.error) {
                            updateStatus(false, null, 0, [], data.error);
                        } else if (data.configured) {
                            updateStatus(false, null, 0, [], "Click Connect to link your site");
                        } else {
                            updateStatus(false, null, 0, [], "Click Connect to link your Moodle site");
                        }
                    })
                    .catch(function(err) {
                        updateStatus(false, null, 0, [], err.message);
                    });
                }

                // Expose testConnection globally so action buttons can call it.
                window.mcTestConnection = testConnection;

                // Expose function to update status with error message (for sync failures).
                window.mcUpdateStatusWithError = function(errorMessage) {
                    statusDot.style.color = "#dc3545";
                    statusText.style.color = "#721c24";
                    statusText.textContent = "Sync failed";
                    syncStatus.innerHTML = "<span style=\"color: #dc3545;\">• " + errorMessage + "</span>";
                };

                // Initial check on page load.
                testConnection();
            })();
            </script>';

        return format_admin_setting($this, $this->visiblename, $html, '', false, '', null, $query);
    }
}
