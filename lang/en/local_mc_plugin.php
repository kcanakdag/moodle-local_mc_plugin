<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'MoodleConnect';
$string['settings'] = 'MoodleConnect Settings';

// Section headings
$string['connection_heading'] = 'Connection';
$string['events_heading'] = 'Monitored Events';
$string['events_heading_desc'] = 'Select which Moodle events to forward to MoodleConnect for triggers and automation.';
$string['advanced_heading'] = 'Advanced';

// Connection settings
$string['moodleconnect_url'] = 'MoodleConnect URL';
$string['moodleconnect_url_desc'] = 'The API URL for MoodleConnect (e.g., https://moodleconnect.com/api)';
$string['site_key'] = 'Site Key';
$string['site_key_desc'] = 'Your unique site key from the MoodleConnect dashboard. <a href="https://moodleconnect.com" target="_blank">Get your site key</a>';
$string['site_key_readonly_desc'] = 'Your unique site key (managed automatically via the Connect button above).';
$string['site_secret'] = 'Site Secret';
$string['site_secret_desc'] = 'Your site secret for HMAC signing. Found in the MoodleConnect dashboard under Site Settings.';
$string['site_secret_readonly_desc'] = 'Your site secret for HMAC signing (managed automatically via the Connect button above).';

// Connection status
$string['connection_status'] = 'Status';
$string['status_connected'] = 'Connected';
$string['status_not_connected'] = 'Not connected';
$string['reconnect_link'] = 'Reconnect';

// Connect button and OAuth flow
$string['connect_heading'] = 'Connect to MoodleConnect';
$string['connect_button'] = 'Connect to MoodleConnect';
$string['reconnect_button'] = 'Reconnect';
$string['connect_button_desc'] = 'Click to connect this Moodle site to your MoodleConnect account. A new tab will open where you can log in and confirm the connection.';
$string['connect_initializing'] = 'Initializing...';
$string['connect_waiting'] = 'Waiting for connection to complete. Please complete the connection in the MoodleConnect tab.';
$string['connect_waiting_btn'] = 'Waiting...';
$string['connect_saving'] = 'Saving credentials...';
$string['connect_success'] = 'Connected successfully! Your site is now linked to MoodleConnect.';
$string['connect_init_failed'] = 'Failed to initialize connection';
$string['connect_popup_blocked'] = 'Pop-up blocked. Please allow pop-ups for this site and try again.';
$string['connect_timeout'] = 'Connection timed out. Please try again.';
$string['connect_token_expired'] = 'Connection token expired. Please try again.';
$string['connect_credentials_retrieved'] = 'Credentials were already retrieved. Please try connecting again.';
$string['connect_save_failed'] = 'Failed to save credentials';

// Event selection
$string['monitored_events'] = 'Events to Monitor';
$string['monitored_events_desc'] = 'These events will be sent to MoodleConnect when they occur.';

// Debug mode
$string['debug_mode'] = 'Debug Mode';
$string['debug_mode_desc'] = 'Show notifications when events are triggered (useful for testing).';

// Primary action buttons
$string['btn_save_sync'] = 'Save and Sync Events';
$string['save_without_sync'] = 'Save without syncing';

// Legacy strings (kept for compatibility)
$string['config_heading'] = 'Configuration';
$string['signin_button'] = 'Sign in to MoodleConnect';
$string['test_event_button'] = 'Send Test Event';
$string['event_sent_success'] = 'Test event sent successfully!';
$string['event_sent_fail'] = 'Failed to send test event: {$a}';
$string['sync_schema_button'] = 'Sync Events to MoodleConnect';
$string['sync_schema_desc'] = 'Sync event schemas to MoodleConnect for trigger configuration.';

// Debug notifications
$string['debug_event_triggered'] = 'MoodleConnect Debug: Event "{$a->event}" triggered and sent.';
