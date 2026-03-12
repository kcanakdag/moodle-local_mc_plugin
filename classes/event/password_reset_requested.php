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
 * Password reset requested event.
 *
 * Fired when a user requests a password reset via the forgot-password form.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\event;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- autoloaded namespaced class.
defined('MOODLE_INTERNAL') || die();

/**
 * Event fired when a user requests a password reset.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class password_reset_requested extends \core\event\base {
    /**
     * Initialise the event.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'user_password_resets';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_password_reset_requested', 'local_mc_plugin');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->relateduserid' requested a password reset.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url|null
     */
    public function get_url() {
        return null;
    }

    /**
     * Return object ID mapping for backup/restore and schema discovery.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'user_password_resets'];
    }

    /**
     * Return other data mapping for backup/restore.
     *
     * The 'reseturl' field is a generated URL, not a database ID,
     * so there is nothing to map for backup/restore.
     *
     * @return false
     */
    public static function get_other_mapping() {
        return false;
    }

    /**
     * Declare the extra fields this event provides in 'other'.
     *
     * Used by dynamic_inspector during schema sync so these fields
     * appear in MoodleConnect without firing a live event first.
     *
     * @return array Field name => ['type' => string].
     */
    public static function get_mc_other_fields() {
        return [
            'reseturl' => ['type' => 'string'],
        ];
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['reseturl'])) {
            throw new \coding_exception('The \'reseturl\' value must be set in other.');
        }
    }
}
