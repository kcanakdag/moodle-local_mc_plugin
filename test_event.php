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
 * Test event endpoint for MoodleConnect plugin.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
use local_mc_plugin\local\moodleconnect_client;

require_login();
require_capability('moodle/site:config', context_system::instance());

$baseurl = new moodle_url('/admin/settings.php', ['section' => 'local_mc_plugin']);

$data = [
    'test' => true,
    'message' => 'This is a test event from Moodle.',
    'user_id' => $USER->id,
    'username' => $USER->username,
    'site_name' => $SITE->fullname,
    'timestamp' => time(),
];

// Use the specific test event method.
$result = moodleconnect_client::send_event('local_mc_plugin\test_event', $data);

if ($result['success']) {
    redirect(
        $baseurl,
        get_string('event_sent_success', 'local_mc_plugin'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} else {
    redirect(
        $baseurl,
        get_string('event_sent_fail', 'local_mc_plugin', $result['message']),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
