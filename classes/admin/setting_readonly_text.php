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
 * Read-only text display setting.
 * Displays a value as non-editable text, useful for showing credentials
 * that are managed automatically via the OAuth connection flow.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Read-only text display setting.
 * Displays a value as non-editable text, useful for showing credentials
 * that are managed automatically via the OAuth connection flow.
 */
class setting_readonly_text extends \admin_setting {

    /** @var bool Whether to mask the value (show asterisks) */
    protected $masked;

    /**
     * Constructor.
     *
     * @param string $name Unique setting name
     * @param string $visiblename Localised label
     * @param string $description Localised description
     * @param bool $masked Whether to mask the value with asterisks
     */
    public function __construct($name, $visiblename, $description, $masked = false) {
        $this->masked = $masked;
        parent::__construct($name, $visiblename, $description, '');
    }

    /**
     * Returns current value of this setting.
     *
     * @return mixed Current value
     */
    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * This setting is read-only, so write does nothing.
     *
     * @param mixed $data Unused
     * @return string Empty string (no error)
     */
    public function write_setting($data) {
        // Read-only setting, don't write anything
        return '';
    }

    /**
     * Returns the HTML for this setting.
     *
     * @param mixed $data Current value
     * @param string $query Search query
     * @return string HTML output
     */
    public function output_html($data, $query = '') {
        $displayValue = $data;
        
        if ($this->masked && !empty($data)) {
            // Show first 4 chars, then asterisks, then last 4 chars
            $len = strlen($data);
            if ($len > 12) {
                $displayValue = substr($data, 0, 4) . str_repeat('•', min($len - 8, 16)) . substr($data, -4);
            } else {
                $displayValue = str_repeat('•', $len);
            }
        }
        
        $html = '<div class="form-text defaultsnext">';
        $html .= '<code style="padding: 4px 8px; background: #f5f5f5; border-radius: 4px; font-family: monospace;">';
        $html .= s($displayValue);
        $html .= '</code>';
        $html .= '</div>';

        return format_admin_setting($this, $this->visiblename, $html, $this->description, true, '', null, $query);
    }
}
