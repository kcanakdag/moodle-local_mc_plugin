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
 * Admin setting to display event limit warning when events are blocked.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\admin;

/**
 * Admin setting to display event limit warning.
 *
 * Shows a warning banner when events are blocked due to exceeding the monthly limit.
 * The warning includes the message from MoodleConnect and a link to upgrade.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_limit_warning extends \admin_setting {
    /**
     * Constructor.
     *
     * @param string $name Unique setting name
     */
    public function __construct($name) {
        parent::__construct($name, '', '', '');
    }

    /**
     * Always returns true - this is a display-only setting.
     *
     * @return bool Always true
     */
    public function get_setting() {
        return true;
    }

    /**
     * Never writes anything - this is a display-only setting.
     *
     * @param mixed $data Unused
     * @return string Always empty string
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * Returns the HTML for the limit warning display.
     *
     * @param mixed $data Unused
     * @param string $query Unused
     * @return string HTML output
     */
    public function output_html($data, $query = '') {
        // Check if events are blocked.
        $blockeduntil = (int) get_config('local_mc_plugin', 'events_blocked_until');

        // Not blocked or block expired.
        if ($blockeduntil <= 0 || time() >= $blockeduntil) {
            return '';
        }

        // Get the notification message.
        $message = get_config('local_mc_plugin', 'events_limit_notification');
        if (empty($message)) {
            $message = get_string('events_blocked_default', 'local_mc_plugin');
        }

        // Get usage info if available.
        $usagejson = get_config('local_mc_plugin', 'events_limit_usage');
        $usage = $usagejson ? json_decode($usagejson, true) : null;

        // Format the blocked until date.
        $blockeduntilstr = userdate($blockeduntil, get_string('strftimedatetime', 'langconfig'));

        // Build the warning HTML.
        $html = '<div class="alert alert-warning" role="alert">';
        $html .= '<h4 class="alert-heading">';
        $html .= '<i class="fa fa-exclamation-triangle mr-2"></i>';
        $html .= get_string('events_blocked_notification', 'local_mc_plugin', '');
        $html .= '</h4>';
        $html .= '<p>' . s($message) . '</p>';

        if ($usage) {
            $html .= '<p class="mb-0">';
            $html .= '<strong>Usage:</strong> ' . (int) $usage['current'] . ' / ' . (int) $usage['limit'];
            $html .= ' (' . (int) $usage['percent'] . '%)';
            $html .= '</p>';
        }

        $html .= '<hr>';
        $html .= '<p class="mb-0">';
        $html .= 'Events will resume automatically on <strong>' . $blockeduntilstr . '</strong>, ';
        $html .= 'or <a href="https://moodleconnect.com/settings" target="_blank">upgrade your plan</a> ';
        $html .= 'to increase your limit immediately.';
        $html .= '</p>';
        $html .= '</div>';

        return $html;
    }
}
