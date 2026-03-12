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
 * Renderable for the bulk sync component.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\output;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Renderable for the bulk user sync component.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_sync implements renderable, templatable {
    /** @var string URL to sync_schema.php */
    private $syncurl;

    /** @var string Session key for CSRF protection */
    private $sesskey;

    /**
     * Constructor.
     *
     * @param string $syncurl URL to sync_schema.php endpoint
     * @param string $sesskey Session key for CSRF protection
     */
    public function __construct(string $syncurl, string $sesskey) {
        $this->syncurl = $syncurl;
        $this->sesskey = $sesskey;
    }

    /**
     * Export data for template rendering.
     *
     * @param renderer_base $output The renderer
     * @return stdClass Data for the template
     */
    public function export_for_template(renderer_base $output): stdClass {
        // Template uses only {{#str}} helpers; no dynamic context needed.
        return new stdClass();
    }

    /**
     * Get JavaScript configuration for AMD module initialization.
     *
     * @return array Configuration array for js_call_amd
     */
    public function get_js_config(): array {
        return [
            'syncUrl' => $this->syncurl,
            'sesskey' => $this->sesskey,
        ];
    }
}
