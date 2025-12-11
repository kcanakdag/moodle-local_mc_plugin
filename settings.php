<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_mc_plugin', get_string('pluginname', 'local_mc_plugin'));

    // ========================================
    // Section: Connection
    // ========================================
    $settings->add(new admin_setting_heading(
        'local_mc_plugin/connection_heading',
        get_string('connection_heading', 'local_mc_plugin'),
        ''
    ));

    // Check connection status
    $site_key = get_config('local_mc_plugin', 'site_key');
    $site_secret = get_config('local_mc_plugin', 'site_secret');
    $is_connected = !empty($site_key) && !empty($site_secret);

    // Connect/Reconnect Button (OAuth-style flow)
    $settings->add(new \local_mc_plugin\admin\setting_connect_button(
        'local_mc_plugin/connect_button',
        $is_connected
    ));

    // Connection Status Display
    $settings->add(new \local_mc_plugin\admin\setting_connection_status(
        'local_mc_plugin/connection_status',
        $is_connected
    ));

    // Site Key and Secret are stored internally but not displayed to users

    // ========================================
    // Section: Monitored Events
    // ========================================
    $settings->add(new admin_setting_heading(
        'local_mc_plugin/events_heading',
        get_string('events_heading', 'local_mc_plugin'),
        get_string('events_heading_desc', 'local_mc_plugin')
    ));

    // Dynamic Event Selection
    $settings->add(new \local_mc_plugin\admin\setting_event_selection(
        'local_mc_plugin/monitored_events',
        get_string('monitored_events', 'local_mc_plugin'),
        get_string('monitored_events_desc', 'local_mc_plugin'),
        '\core\event\user_created, \core\event\course_created'
    ));

    // ========================================
    // Section: Advanced
    // ========================================
    $settings->add(new admin_setting_heading(
        'local_mc_plugin/advanced_heading',
        get_string('advanced_heading', 'local_mc_plugin'),
        ''
    ));

    // Debug Mode
    $settings->add(new admin_setting_configcheckbox(
        'local_mc_plugin/debug_mode',
        get_string('debug_mode', 'local_mc_plugin'),
        get_string('debug_mode_desc', 'local_mc_plugin'),
        0
    ));

    // ========================================
    // Primary Action Button (Save and Sync)
    // ========================================
    $sync_url = (new moodle_url('/local/mc_plugin/sync_schema.php'))->out(false);
    
    $settings->add(new \local_mc_plugin\admin\setting_action_buttons(
        'local_mc_plugin/action_buttons',
        $is_connected,
        $sync_url
    ));

    $ADMIN->add('localplugins', $settings);
}
