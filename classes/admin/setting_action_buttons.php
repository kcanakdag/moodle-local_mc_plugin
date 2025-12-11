<?php
namespace local_mc_plugin\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Custom admin setting that renders the primary action button.
 */
class setting_action_buttons extends \admin_setting {

    private $sync_url;
    private $ajax_save_url;
    private $sesskey;

    public function __construct($name, $is_connected, $sync_url) {
        global $CFG;
        $this->sync_url = $sync_url;
        $this->ajax_save_url = (new \moodle_url('/local/mc_plugin/ajax_save.php'))->out(false);
        $this->sesskey = sesskey();
        parent::__construct($name, '', '', '');
    }

    public function get_setting() {
        return true;
    }

    public function write_setting($data) {
        return '';
    }

    public function output_html($data, $query = '') {
        $btn_label = get_string('btn_save_sync', 'local_mc_plugin');
        
        $html = '
        <div class="form-item row" id="moodleconnect-action-section">
            <div class="form-label col-sm-3"></div>
            <div class="form-setting col-sm-9">
                <div id="moodleconnect-primary-action" style="padding-top: 15px; border-top: 1px solid #dee2e6;">
                    <div id="mc-action-result" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 15px;"></div>
                    
                    <button type="button" id="mc-primary-btn" class="btn btn-primary" style="padding: 10px 24px; font-size: 15px;">
                        <span id="mc-btn-text">' . s($btn_label) . '</span>
                        <span id="mc-btn-spinner" style="display: none; margin-left: 8px;">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
        
        <style>
        /* Hide Moodle default save button since we handle saving via AJAX */
        #adminsettings .row > .offset-sm-3 > button[type="submit"],
        #adminsettings > .row:last-child,
        form#adminsettings > div.row:has(button[type="submit"]) { display: none !important; }
        </style>
        
        <script>
        (function() {
            var primaryBtn = document.getElementById("mc-primary-btn");
            var btnText = document.getElementById("mc-btn-text");
            var btnSpinner = document.getElementById("mc-btn-spinner");
            var resultDiv = document.getElementById("mc-action-result");
            var syncUrl = "' . $this->sync_url . '";
            var ajaxSaveUrl = "' . $this->ajax_save_url . '";
            var sesskey = "' . $this->sesskey . '";
            var btnLabel = "' . s($btn_label) . '";
            
            function showResult(success, message) {
                resultDiv.style.display = "block";
                resultDiv.style.background = success ? "#d4edda" : "#f8d7da";
                resultDiv.style.color = success ? "#155724" : "#721c24";
                resultDiv.innerHTML = (success ? "✓ " : "✗ ") + message;
            }
            
            function setLoading(loading) {
                primaryBtn.disabled = loading;
                btnSpinner.style.display = loading ? "inline-block" : "none";
            }
            
            function getFormValues() {
                var values = {};
                // Site key/secret may be in form (for connected users) or stored in config
                var siteKeyInput = document.querySelector("input[name=\"s_local_mc_plugin_site_key\"]");
                if (siteKeyInput && siteKeyInput.value) values.site_key = siteKeyInput.value;
                var siteSecretInput = document.querySelector("input[name=\"s_local_mc_plugin_site_secret\"]");
                if (siteSecretInput && siteSecretInput.value) values.site_secret = siteSecretInput.value;
                var eventsInput = document.querySelector("input[name=\"s_local_mc_plugin_monitored_events\"]");
                if (eventsInput) values.monitored_events = eventsInput.value;
                var debugInput = document.querySelector("input[name=\"s_local_mc_plugin_debug_mode\"]");
                if (debugInput) values.debug_mode = debugInput.checked ? 1 : 0;
                return values;
            }
            
            function saveSettings(callback) {
                var values = getFormValues();
                
                var params = new URLSearchParams();
                params.append("action", "save");
                params.append("sesskey", sesskey);
                for (var key in values) {
                    if (values.hasOwnProperty(key)) {
                        params.append(key, values[key]);
                    }
                }
                
                fetch(ajaxSaveUrl, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: params.toString()
                })
                .then(function(r) { return r.json(); })
                .then(function(data) { callback(data.success, data.message); })
                .catch(function(err) { callback(false, "Save failed: " + err.message); });
            }
            
            function syncEvents(callback) {
                fetch(syncUrl + "?action=sync", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    callback(data.success, data.success ? 
                        "Synced " + (data.event_count || 0) + " event(s) to MoodleConnect" : 
                        data.message);
                })
                .catch(function(err) { callback(false, "Sync failed: " + err.message); });
            }
            
            function updateConnectionStatus() {
                // Use the global function exposed by connection status component
                // Pass true to skip save (we already saved)
                if (typeof window.mcTestConnection === "function") {
                    window.mcTestConnection(true);
                }
            }
            
            function updateConnectionStatusWithError(errorMessage) {
                // Update the status display to show the sync error
                if (typeof window.mcUpdateStatusWithError === "function") {
                    window.mcUpdateStatusWithError(errorMessage);
                }
            }
            
            primaryBtn.addEventListener("click", function() {
                resultDiv.style.display = "none";
                setLoading(true);
                btnText.textContent = "Saving...";
                
                saveSettings(function(saveSuccess, saveError) {
                    if (!saveSuccess) {
                        setLoading(false);
                        btnText.textContent = btnLabel;
                        showResult(false, saveError || "Failed to save settings");
                        return;
                    }
                    
                    btnText.textContent = "Syncing...";
                    
                    syncEvents(function(syncSuccess, syncMessage) {
                        setLoading(false);
                        btnText.textContent = btnLabel;
                        
                        if (syncSuccess) {
                            showResult(true, "Settings saved. " + syncMessage);
                            updateConnectionStatus();
                        } else {
                            showResult(false, "Settings saved, but sync failed: " + syncMessage);
                            // Also update the connection status to show the error
                            updateConnectionStatusWithError(syncMessage);
                        }
                    });
                });
            });
        })();
        </script>';
        
        return $html;
    }
}
