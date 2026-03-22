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
 * Renderable for the action buttons component.
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
 * Renderable for the action buttons component.
 *
 * Prepares data for the action_buttons Mustache template.
 * URL plumbing removed: JS routes via core/ajax methodnames instead of URLs.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_buttons implements renderable, templatable {
    /**
     * Constructor.
     */
    public function __construct() {
    }

    /**
     * Export data for template rendering.
     *
     * @param renderer_base $output The renderer
     * @return stdClass Data for the template
     */
    public function export_for_template(renderer_base $output): stdClass {
        return new stdClass();
    }

    /**
     * Get JavaScript configuration for AMD module initialization.
     *
     * @return array Configuration array for js_call_amd
     */
    public function get_js_config(): array {
        return [];
    }
}
