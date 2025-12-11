<?php
namespace local_mc_plugin\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/mc_plugin/lib.php');

/**
 * Custom admin setting that renders the "Connect to MoodleConnect" button
 * and handles the OAuth-style connection flow with polling.
 */
class setting_connect_button extends \admin_setting {

    private $is_connected;
    private $connect_url;
    private $ajax_save_url;
    private $api_url;
    private $sesskey;

    public function __construct($name, $is_connected) {
        global $CFG;
        
        $this->is_connected = $is_connected;
        $this->connect_url = (new \moodle_url('/local/mc_plugin/connect.php'))->out(false);
        $this->ajax_save_url = (new \moodle_url('/local/mc_plugin/ajax_save.php'))->out(false);
        $this->api_url = local_mc_plugin_get_api_url();
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
        $connect_label = get_string('connect_button', 'local_mc_plugin');
        $reconnect_label = get_string('reconnect_button', 'local_mc_plugin');
        $btn_label = $this->is_connected ? $reconnect_label : $connect_label;
        
        // Get frontend URL (supports separate config for development)
        $frontend_url = local_mc_plugin_get_frontend_url();
        
        $html = '
        <div class="form-item row" id="moodleconnect-connect-section">
            <div class="form-label col-sm-3">
                <label>' . get_string('connect_heading', 'local_mc_plugin') . '</label>
            </div>
            <div class="form-setting col-sm-9">
                <div id="mc-connect-container">
                    <div id="mc-connect-status" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 15px;">
                        <span id="mc-connect-status-icon"></span>
                        <span id="mc-connect-status-text"></span>
                    </div>
                    
                    <button type="button" id="mc-connect-btn" class="btn ' . ($this->is_connected ? 'btn-outline-primary' : 'btn-primary') . '" style="padding: 10px 24px; font-size: 15px;">
                        <span id="mc-connect-btn-text">' . s($btn_label) . '</span>
                        <span id="mc-connect-btn-spinner" style="display: none; margin-left: 8px;">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                        </span>
                    </button>
                    
                    <p class="form-text text-muted" style="margin-top: 8px;">
                        ' . get_string('connect_button_desc', 'local_mc_plugin') . '
                    </p>
                </div>
            </div>
        </div>
        
        <script>
        (function() {
            var POLL_INTERVAL = 3000;
            var MAX_POLL_ATTEMPTS = 60;
            
            var connectBtn = document.getElementById("mc-connect-btn");
            var btnText = document.getElementById("mc-connect-btn-text");
            var btnSpinner = document.getElementById("mc-connect-btn-spinner");
            var statusDiv = document.getElementById("mc-connect-status");
            var statusIcon = document.getElementById("mc-connect-status-icon");
            var statusText = document.getElementById("mc-connect-status-text");
            
            var connectUrl = "' . $this->connect_url . '";
            var saveUrl = "' . $this->ajax_save_url . '";
            var apiUrl = "' . $this->api_url . '";
            var frontendUrl = "' . $frontend_url . '";
            var sesskey = "' . $this->sesskey . '";
            var isConnected = ' . ($this->is_connected ? 'true' : 'false') . ';
            
            var pollTimer = null;
            var pollAttempts = 0;
            var currentToken = null;
            
            function showStatus(type, message) {
                statusDiv.style.display = "block";
                if (type === "waiting") {
                    statusDiv.style.background = "#fff3cd";
                    statusDiv.style.color = "#856404";
                    statusIcon.innerHTML = "<span class=\"spinner-border spinner-border-sm\" style=\"margin-right: 8px;\"></span>";
                } else if (type === "success") {
                    statusDiv.style.background = "#d4edda";
                    statusDiv.style.color = "#155724";
                    statusIcon.innerHTML = "✓ ";
                } else if (type === "error") {
                    statusDiv.style.background = "#f8d7da";
                    statusDiv.style.color = "#721c24";
                    statusIcon.innerHTML = "✗ ";
                }
                statusText.textContent = message;
            }
            
            function hideStatus() {
                statusDiv.style.display = "none";
            }
            
            function setLoading(loading) {
                connectBtn.disabled = loading;
                btnSpinner.style.display = loading ? "inline-block" : "none";
            }
            
            function stopPolling() {
                if (pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            }
            
            function saveCredentials(siteKey, siteSecret) {
                showStatus("waiting", "' . get_string('connect_saving', 'local_mc_plugin') . '");
                
                var params = new URLSearchParams();
                params.append("action", "save");
                params.append("sesskey", sesskey);
                params.append("site_key", siteKey);
                params.append("site_secret", siteSecret);
                
                fetch(saveUrl, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: params.toString()
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    setLoading(false);
                    if (data.success) {
                        showStatus("success", "' . get_string('connect_success', 'local_mc_plugin') . '");
                        btnText.textContent = "' . s($reconnect_label) . '";
                        connectBtn.classList.remove("btn-primary");
                        connectBtn.classList.add("btn-outline-primary");
                        isConnected = true;
                        
                        // Update the connection status display
                        if (typeof window.mcTestConnection === "function") {
                            setTimeout(function() {
                                window.mcTestConnection(true);
                            }, 500);
                        }
                        

                    } else {
                        showStatus("error", data.message || "' . get_string('connect_save_failed', 'local_mc_plugin') . '");
                    }
                })
                .catch(function(err) {
                    setLoading(false);
                    showStatus("error", "' . get_string('connect_save_failed', 'local_mc_plugin') . ': " + err.message);
                });
            }
            
            function pollStatus() {
                pollAttempts++;
                
                if (pollAttempts > MAX_POLL_ATTEMPTS) {
                    stopPolling();
                    setLoading(false);
                    showStatus("error", "' . get_string('connect_timeout', 'local_mc_plugin') . '");
                    return;
                }
                
                fetch(apiUrl + "/connect/status?token=" + encodeURIComponent(currentToken))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.status === "completed") {
                        stopPolling();
                        
                        if (data.site_key && data.site_secret) {
                            saveCredentials(data.site_key, data.site_secret);
                        } else {
                            setLoading(false);
                            showStatus("error", "' . get_string('connect_credentials_retrieved', 'local_mc_plugin') . '");
                        }
                    } else if (data.status === "expired") {
                        stopPolling();
                        setLoading(false);
                        showStatus("error", "' . get_string('connect_token_expired', 'local_mc_plugin') . '");
                    }
                    // If pending, continue polling
                })
                .catch(function(err) {
                    // Network error, but continue polling
                    console.warn("Poll failed:", err);
                });
            }
            
            function startConnection() {
                hideStatus();
                setLoading(true);
                btnText.textContent = "' . get_string('connect_initializing', 'local_mc_plugin') . '";
                pollAttempts = 0;
                
                var params = new URLSearchParams();
                params.append("action", "init");
                params.append("sesskey", sesskey);
                
                fetch(connectUrl, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: params.toString()
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.token) {
                        currentToken = data.token;
                        
                        // Open MoodleConnect in a new tab
                        var connectPageUrl = frontendUrl + "/connect?token=" + encodeURIComponent(data.token);
                        var connectWindow = window.open(connectPageUrl, "_blank");
                        
                        if (!connectWindow) {
                            setLoading(false);
                            btnText.textContent = isConnected ? "' . s($reconnect_label) . '" : "' . s($connect_label) . '";
                            showStatus("error", "' . get_string('connect_popup_blocked', 'local_mc_plugin') . '");
                            return;
                        }
                        
                        btnText.textContent = "' . get_string('connect_waiting_btn', 'local_mc_plugin') . '";
                        showStatus("waiting", "' . get_string('connect_waiting', 'local_mc_plugin') . '");
                        
                        // Start polling
                        pollTimer = setInterval(pollStatus, POLL_INTERVAL);
                    } else {
                        setLoading(false);
                        btnText.textContent = isConnected ? "' . s($reconnect_label) . '" : "' . s($connect_label) . '";
                        showStatus("error", data.message || "' . get_string('connect_init_failed', 'local_mc_plugin') . '");
                    }
                })
                .catch(function(err) {
                    setLoading(false);
                    btnText.textContent = isConnected ? "' . s($reconnect_label) . '" : "' . s($connect_label) . '";
                    showStatus("error", "' . get_string('connect_init_failed', 'local_mc_plugin') . ': " + err.message);
                });
            }
            
            connectBtn.addEventListener("click", startConnection);
        })();
        </script>';
        
        return $html;
    }
}
