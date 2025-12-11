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
 * Event discovery service for dynamically discovering all Moodle events.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Service class for discovering all available Moodle events dynamically.
 */
class event_discovery {
    /** @var string Cache key for event list */
    private const CACHE_KEY = 'event_list';

    /** @var int Cache TTL in seconds (1 hour) */
    private const CACHE_TTL = 3600;

    /**
     * Get all available Moodle events.
     */
    public function get_all_events(): array {
        $cache = \cache::make('local_mc_plugin', 'mc_metadata');
        $cached = $cache->get(self::CACHE_KEY);

        if ($cached !== false) {
            return $cached;
        }

        $events = $this->discover_events();
        $cache->set(self::CACHE_KEY, $events);

        return $events;
    }

    /**
     * Get events grouped by category.
     */
    public function get_events_by_category(): array {
        $events = $this->get_all_events();
        $categorized = [];

        foreach ($events as $event) {
            $category = $event['category'];
            if (!isset($categorized[$category])) {
                $categorized[$category] = [];
            }
            $categorized[$category][] = $event;
        }

        ksort($categorized);

        foreach ($categorized as $category => $categoryevents) {
            usort($categoryevents, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            $categorized[$category] = $categoryevents;
        }

        return $categorized;
    }

    /**
     * Search events by query string.
     */
    public function search_events(string $query): array {
        if (empty(trim($query))) {
            return $this->get_all_events();
        }

        $events = $this->get_all_events();
        $query = strtolower(trim($query));
        $results = [];

        foreach ($events as $event) {
            $searchable = strtolower(
                $event['name'] . ' ' .
                $event['class'] . ' ' .
                $event['component'] . ' ' .
                ($event['description'] ?? '')
            );

            if (strpos($searchable, $query) !== false) {
                $results[] = $event;
            }
        }

        return $results;
    }

    /**
     * Get detailed information about a specific event.
     */
    public function get_event_info(string $eventclass): ?array {
        $events = $this->get_all_events();

        foreach ($events as $event) {
            if ($event['class'] === $eventclass) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Clear the event cache.
     */
    public function clear_cache(): void {
        $cache = \cache::make('local_mc_plugin', 'mc_metadata');
        $cache->delete(self::CACHE_KEY);
    }

    /**
     * Discover all events from Moodle components.
     */
    private function discover_events(): array {
        global $CFG;
        $events = [];

        $olddebug = $CFG->debug;
        $olddisplay = $CFG->debugdisplay;
        $CFG->debug = 0;
        $CFG->debugdisplay = false;

        $eventclasses = \core_component::get_component_classes_in_namespace(null, 'event');

        $CFG->debug = $olddebug;
        $CFG->debugdisplay = $olddisplay;

        foreach ($eventclasses as $eventclass => $path) {
            if (!is_subclass_of($eventclass, '\core\event\base')) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($eventclass);
                if ($reflection->isAbstract()) {
                    continue;
                }
                
                $doccomment = $reflection->getDocComment();
                if ($doccomment && strpos($doccomment, '@deprecated') !== false) {
                    continue;
                }
            } catch (\ReflectionException $e) {
                continue;
            }

            $events[] = [
                'class' => $eventclass,
                'name' => self::get_friendly_name($eventclass),
                'category' => $this->get_category($eventclass),
                'component' => $this->get_component($eventclass),
                'description' => $this->get_description($eventclass),
            ];
        }

        usort($events, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $events;
    }

    /**
     * Convert event class name to friendly readable name.
     */
    public static function get_friendly_name(string $eventclass): string {
        $parts = explode('\\', $eventclass);
        $name = end($parts);
        $name = str_replace('_', ' ', $name);
        $name = ucwords($name);
        return $name;
    }

    /**
     * Get category name for an event based on its component.
     */
    private function get_category(string $eventclass): string {
        $component = $this->get_component($eventclass);

        if ($component === 'core') {
            return 'Core System Events';
        }

        $parts = explode('_', $component, 2);
        $type = $parts[0];
        $name = isset($parts[1]) ? $parts[1] : '';

        switch ($type) {
            case 'mod':
                return ucfirst($name) . ' Activity Events';
            case 'block':
                return ucfirst($name) . ' Block Events';
            case 'local':
                return ucfirst($name) . ' Local Plugin Events';
            case 'tool':
                return ucfirst($name) . ' Admin Tool Events';
            case 'report':
                return ucfirst($name) . ' Report Events';
            case 'enrol':
                return ucfirst($name) . ' Enrollment Events';
            case 'auth':
                return ucfirst($name) . ' Authentication Events';
            case 'theme':
                return ucfirst($name) . ' Theme Events';
            default:
                return ucfirst($component) . ' Events';
        }
    }

    /**
     * Extract component name from event class.
     */
    private function get_component(string $eventclass): string {
        $parts = explode('\\', $eventclass);

        if (count($parts) > 0) {
            $component = $parts[0];

            if ($component === 'core') {
                return 'core';
            }

            return $component;
        }

        return 'unknown';
    }

    /**
     * Get event description from the event class.
     */
    private function get_description(string $eventclass): string {
        try {
            if (method_exists($eventclass, 'get_description')) {
                return '';
            }
        } catch (\Exception $e) {
        }

        return '';
    }
}
