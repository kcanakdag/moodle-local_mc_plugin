<?php
/**
 * AJAX endpoint for OAuth-style connection flow.
 * 
 * This endpoint handles:
 * - Generating connection tokens via POST to MoodleConnect /connect/init
 * - Returning the token to JavaScript for the polling flow
 * 
 * @package    local_mc_plugin
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

// Verify sesskey for security
require_sesskey();

header('Content-Type: application/json');

$action = required_param('action', PARAM_ALPHA);

if ($action === 'init') {
    // Generate a connection token by calling MoodleConnect API
    $base_url = local_mc_plugin_get_api_url();
    $init_url = $base_url . '/connect/init';
    
    // Get Moodle site info
    $moodle_url = $CFG->wwwroot;
    $moodle_site_name = $SITE->fullname;
    
    $payload = [
        'moodle_url' => $moodle_url,
        'moodle_site_name' => $moodle_site_name
    ];
    
    $json = json_encode($payload);
    
    $ch = curl_init($init_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json)
    ]);
    
    $result = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($result === false) {
        echo json_encode([
            'success' => false,
            'message' => 'Connection failed: ' . $curl_error
        ]);
        exit;
    }
    
    $response = json_decode($result, true);
    
    if ($httpcode >= 200 && $httpcode < 300 && isset($response['token'])) {
        echo json_encode([
            'success' => true,
            'token' => $response['token'],
            'expires_at' => $response['expires_at'] ?? null
        ]);
    } else {
        $error_message = $response['error'] ?? $response['message'] ?? "HTTP $httpcode";
        echo json_encode([
            'success' => false,
            'message' => $error_message
        ]);
    }
    exit;
}

// Unknown action
echo json_encode([
    'success' => false,
    'message' => 'Unknown action: ' . $action
]);
