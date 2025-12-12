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
 * English language strings for MoodleConnect plugin.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['advanced_heading'] = 'Advanced';
$string['btn_save_sync'] = 'Save and Sync Events';
$string['config_heading'] = 'Configuration';
$string['connect_button'] = 'Connect to MoodleConnect';
$string['connect_button_desc'] = 'Click to connect this Moodle site to your MoodleConnect account. A new tab will open where you can log in and confirm the connection.';
$string['connect_credentials_retrieved'] = 'Credentials were already retrieved. Please try connecting again.';
$string['connect_heading'] = 'Connect to MoodleConnect';
$string['connect_init_failed'] = 'Failed to initialize connection';
$string['connect_initializing'] = 'Initializing...';
$string['connect_popup_blocked'] = 'Pop-up blocked. Please allow pop-ups for this site and try again.';
$string['connect_save_failed'] = 'Failed to save credentials';
$string['connect_saving'] = 'Saving credentials...';
$string['connect_success'] = 'Connected successfully! Your site is now linked to MoodleConnect.';
$string['connect_timeout'] = 'Connection timed out. Please try again.';
$string['connect_token_expired'] = 'Connection token expired. Please try again.';
$string['connect_waiting'] = 'Waiting for connection to complete. Please complete the connection in the MoodleConnect tab.';
$string['connect_waiting_btn'] = 'Waiting...';
$string['connection_heading'] = 'Connection';
$string['connection_status'] = 'Status';
$string['debug_api_url'] = 'API URL';
$string['debug_configuration'] = 'Configuration';
$string['debug_debug_mode'] = 'Debug Mode';
$string['debug_enable_mode'] = 'Enable debug mode and trigger an event to create the log.';
$string['debug_event_observers'] = 'Event Observers';
$string['debug_event_triggered'] = 'MoodleConnect Debug: Event "{$a->event}" triggered and sent.';
$string['debug_found_observers'] = 'Found {$a} observer(s):';
$string['debug_heading'] = 'MoodleConnect Debug';
$string['debug_log'] = 'Debug Log';
$string['debug_mode'] = 'Debug Mode';
$string['debug_mode_desc'] = 'Show notifications when events are triggered (useful for testing).';
$string['debug_monitored_events'] = 'Monitored Events';
$string['debug_none'] = 'None';
$string['debug_no_log_found'] = 'No debug log found at: {$a}';
$string['debug_not_set'] = 'Not set';
$string['debug_off'] = 'OFF';
$string['debug_on'] = 'ON';
$string['debug_setting'] = 'Setting';
$string['debug_site_key'] = 'Site Key';
$string['debug_value'] = 'Value';
$string['debug_warning_no_observers'] = 'WARNING: No local_mc_plugin observers found! Did you purge caches?';
$string['error_connection_failed'] = 'Connection failed: {$a}';
$string['error_invalid_site_key_format'] = 'Invalid site_key format';
$string['error_invalid_site_secret_format'] = 'Invalid site_secret format';
$string['error_loading_events'] = 'Error loading events: {$a}';
$string['error_missing_site_key'] = 'Missing Site Key';
$string['error_missing_site_secret'] = 'Missing Site Secret';
$string['error_no_site_key'] = 'No site key configured';
$string['error_unknown_action'] = 'Unknown action: {$a}';
$string['event_all_synced'] = 'all synced';
$string['event_deselect_visible'] = 'Deselect Visible';
$string['event_new'] = 'new';
$string['event_removed'] = 'removed';
$string['event_search_placeholder'] = 'Search events...';
$string['event_select_visible'] = 'Select Visible';
$string['event_selected_count'] = '{$a} selected';
$string['event_sent_fail'] = 'Failed to send test event: {$a}';
$string['event_sent_success'] = 'Test event sent successfully!';
$string['events_heading'] = 'Monitored Events';
$string['events_heading_desc'] = 'Select which Moodle events to forward to MoodleConnect for triggers and automation.';
$string['failed_event_sent'] = 'MoodleConnect: Event \'{$a->event}\' failed - {$a->message}';
$string['monitored_events'] = 'Events to Monitor';
$string['monitored_events_desc'] = 'These events will be sent to MoodleConnect when they occur.';
$string['moodleconnect_url'] = 'MoodleConnect URL';
$string['moodleconnect_url_desc'] = 'The API URL for MoodleConnect (e.g., https://moodleconnect.com/api)';
$string['pluginname'] = 'MoodleConnect';
$string['privacy:export:note'] = 'This plugin does not store personal data locally in Moodle. Event data is transmitted to the MoodleConnect service (https://moodleconnect.com) for integration with external tools. To request export or deletion of data transmitted to MoodleConnect, please contact the service administrator or visit the MoodleConnect dashboard.';
$string['privacy:metadata:moodleconnect_api'] = 'The MoodleConnect plugin transmits event data to the external MoodleConnect service for integration with third-party tools. This data is sent in real-time when monitored events occur in Moodle.';
$string['privacy:metadata:moodleconnect_api:courseid'] = 'The course ID associated with the event (if applicable).';
$string['privacy:metadata:moodleconnect_api:coursename'] = 'The course name associated with the event (if applicable).';
$string['privacy:metadata:moodleconnect_api:email'] = 'The email address of the user associated with the event.';
$string['privacy:metadata:moodleconnect_api:eventdata'] = 'Additional event-specific data that may include personal information depending on the event type and configuration.';
$string['privacy:metadata:moodleconnect_api:eventtype'] = 'The type of Moodle event that occurred (e.g., user_created, course_viewed).';
$string['privacy:metadata:moodleconnect_api:firstname'] = 'The first name of the user associated with the event.';
$string['privacy:metadata:moodleconnect_api:idnumber'] = 'The ID number of the user (if set) associated with the event.';
$string['privacy:metadata:moodleconnect_api:lastname'] = 'The last name of the user associated with the event.';
$string['privacy:metadata:moodleconnect_api:timecreated'] = 'The timestamp when the event occurred in Moodle.';
$string['privacy:metadata:moodleconnect_api:userid'] = 'The user ID from Moodle, used to identify the user associated with the event.';
$string['privacy:metadata:moodleconnect_api:username'] = 'The username of the user associated with the event.';
$string['reconnect_button'] = 'Reconnect';
$string['reconnect_link'] = 'Reconnect';
$string['save_without_sync'] = 'Save without syncing';
$string['sent'] = 'Sent';
$string['sent_async'] = 'Sent (async)';
$string['settings'] = 'MoodleConnect Settings';
$string['signin_button'] = 'Sign in to MoodleConnect';
$string['site_key'] = 'Site Key';
$string['site_key_desc'] = 'Your unique site key from the MoodleConnect dashboard. <a href="https://moodleconnect.com" target="_blank">Get your site key</a>';
$string['site_key_readonly_desc'] = 'Your unique site key (managed automatically via the Connect button above).';
$string['site_secret'] = 'Site Secret';
$string['site_secret_desc'] = 'Your site secret for HMAC signing. Found in the MoodleConnect dashboard under Site Settings.';
$string['site_secret_readonly_desc'] = 'Your site secret for HMAC signing (managed automatically via the Connect button above).';
$string['status_connected'] = 'Connected';
$string['status_not_connected'] = 'Not connected';
$string['success'] = 'Success';
$string['success_event_sent'] = 'MoodleConnect: Event \'{$a}\' sent successfully';
$string['success_settings_saved'] = 'Settings saved';
$string['sync_back_to_settings'] = 'Back to Settings';
$string['sync_configure_first'] = 'Please configure your Site Key in the plugin settings first.';
$string['sync_go_to_settings'] = 'Go to Settings';
$string['sync_monitored_events_label'] = 'Monitored Events: {$a}';
$string['sync_schema_button'] = 'Sync Events to MoodleConnect';
$string['sync_schema_desc'] = 'Sync event schemas to MoodleConnect for trigger configuration.';
$string['sync_schema_heading'] = 'Event Schema Sync';
$string['sync_site_key_label'] = 'Site Key: {$a}';
$string['test_event_button'] = 'Send Test Event';
