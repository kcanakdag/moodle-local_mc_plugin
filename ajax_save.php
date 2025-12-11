<?php
/**
 * AJAX endpoint to save plugin settings.
 * 
 * @package    local_mc_plugin
 */

require_once('../../config.php');
require_login();
require_sesskey();
require_capability('moodle/site:config', context_system::instance());

header('Content-Type: application/json');

$action = optional_param('action', '', PARAM_ALPHA);
// Use PARAM_RAW for site_key/site_secret as they contain base64url characters (-, _)
$site_key = optional_param('site_key', null, PARAM_RAW);
$site_secret = optional_param('site_secret', null, PARAM_RAW);

// Validate site_key and site_secret format (base64url: alphanumeric, -, _)
if ($site_key !== null && !preg_match('/^[A-Za-z0-9_-]+$/', $site_key)) {
    echo json_encode(['success' => false, 'message' => 'Invalid site_key format']);
    exit;
}
if ($site_secret !== null && !preg_match('/^[A-Za-z0-9_-]+$/', $site_secret)) {
    echo json_encode(['success' => false, 'message' => 'Invalid site_secret format']);
    exit;
}
$monitored_events = optional_param('monitored_events', null, PARAM_RAW);
$debug_mode = optional_param('debug_mode', null, PARAM_INT);

if ($action === 'save') {
    $saved = [];
    
    if ($site_key !== null) {
        set_config('site_key', $site_key, 'local_mc_plugin');
        $saved[] = 'site_key';
    }
    
    if ($site_secret !== null) {
        set_config('site_secret', $site_secret, 'local_mc_plugin');
        $saved[] = 'site_secret';
    }
    
    if ($monitored_events !== null) {
        set_config('monitored_events', $monitored_events, 'local_mc_plugin');
        $saved[] = 'monitored_events';
    }
    
    if ($debug_mode !== null) {
        set_config('debug_mode', $debug_mode, 'local_mc_plugin');
        $saved[] = 'debug_mode';
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Settings saved',
        'saved' => $saved
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Unknown action'
    ]);
}
