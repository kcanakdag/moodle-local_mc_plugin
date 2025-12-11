<?php
require_once('../../config.php');
use local_mc_plugin\local\moodleconnect_client;

require_login();
require_capability('moodle/site:config', context_system::instance());

$base_url = new moodle_url('/admin/settings.php', ['section' => 'local_mc_plugin']);

$data = [
    'test' => true,
    'message' => 'This is a test event from Moodle',
    'user_id' => $USER->id,
    'username' => $USER->username,
    'site_name' => $SITE->fullname,
    'timestamp' => time()
];

// Use the specific test event method
$result = moodleconnect_client::send_event('local_mc_plugin\test_event', $data);

if ($result['success']) {
    redirect($base_url, get_string('event_sent_success', 'local_mc_plugin'), null, \core\output\notification::NOTIFY_SUCCESS);
} else {
    redirect($base_url, get_string('event_sent_fail', 'local_mc_plugin', $result['message']), null, \core\output\notification::NOTIFY_ERROR);
}
