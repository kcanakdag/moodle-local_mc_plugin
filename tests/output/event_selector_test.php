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
 * Property tests for event_selector renderable.
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\output;

/**
 * Property tests for event_selector renderable.
 *
 * Tests the correctness properties defined in the design document:
 * - Property 4: Event selector grouping correctness
 * - Property 5: Event selector selection state preservation
 *
 * @package    local_mc_plugin
 * @category   test
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mc_plugin\output\event_selector
 */
final class event_selector_test extends \advanced_testcase {
    /**
     * Generate a random set of events for testing.
     *
     * @param int $seed Random seed for reproducibility
     * @return array Array of events
     */
    private function generate_events(int $seed): array {
        // Use seed for reproducible "random" data.
        mt_srand($seed);

        $components = ['core', 'mod_assign', 'mod_quiz', 'mod_forum', 'tool_log', 'auth_manual'];
        $eventnames = ['created', 'updated', 'deleted', 'viewed', 'submitted', 'graded'];

        $events = [];
        $numevents = mt_rand(5, 30);

        for ($i = 0; $i < $numevents; $i++) {
            $component = $components[mt_rand(0, count($components) - 1)];
            $eventname = $eventnames[mt_rand(0, count($eventnames) - 1)];
            $classname = "\\{$component}\\event\\{$eventname}_{$i}";

            $events[] = [
                'class' => $classname,
                'name' => ucfirst($eventname) . ' ' . $i,
                'component' => $component,
            ];
        }

        return $events;
    }

    /**
     * Generate a random selection of events.
     *
     * @param array $events All available events
     * @param int $seed Random seed for reproducibility
     * @return array Selected event class names
     */
    private function generate_selection(array $events, int $seed): array {
        mt_srand($seed);

        $selected = [];
        foreach ($events as $event) {
            // Randomly select about 30% of events.
            if (mt_rand(0, 100) < 30) {
                $selected[] = $event['class'];
            }
        }

        return $selected;
    }

    /**
     * Data provider for property tests with various event configurations.
     *
     * Generates 100+ test cases with different combinations of events and selections.
     *
     * @return array Test data
     */
    public static function event_configuration_provider(): array {
        $testcases = [];

        // Generate 100+ test cases with different seeds.
        for ($i = 0; $i < 110; $i++) {
            $testcases["seed_{$i}"] = [$i];
        }

        return $testcases;
    }

    /**
     * **Feature: mustache-templates-refactor, Property 4: Event selector grouping correctness**
     *
     * *For any* list of events with component attributes, the event_selector
     * renderable SHALL group events by component and sort categories alphabetically.
     *
     * **Validates: Requirements 2.5**
     *
     * @dataProvider event_configuration_provider
     * @param int $seed Random seed for test data generation
     */
    public function test_property_grouping_correctness(int $seed): void {
        $this->resetAfterTest(true);

        // Generate test data.
        $events = $this->generate_events($seed);
        $selected = $this->generate_selection($events, $seed + 1000);

        // Create the renderable.
        $selector = new event_selector(
            'test_input_id',
            's_local_mc_plugin_monitored_events',
            $events,
            $selected
        );

        // Create a mock renderer.
        $page = new \moodle_page();
        $renderer = new \renderer_base($page, RENDERER_TARGET_GENERAL);

        // Export the template data.
        $data = $selector->export_for_template($renderer);

        // Property 4a: All events must be grouped by component.
        $expectedgroups = [];
        foreach ($events as $event) {
            $component = $event['component'];
            if (!isset($expectedgroups[$component])) {
                $expectedgroups[$component] = [];
            }
            $expectedgroups[$component][] = $event['class'];
        }

        // Verify each category contains the correct events.
        $actualgroups = [];
        foreach ($data->categories as $category) {
            $componentkey = $this->reverse_format_category_name($category->name);
            $actualgroups[$componentkey] = [];
            foreach ($category->events as $event) {
                $actualgroups[$componentkey][] = $event->classname;
            }
        }

        foreach ($expectedgroups as $component => $expectedclasses) {
            $this->assertArrayHasKey(
                $component,
                $actualgroups,
                "Category for component '{$component}' must exist"
            );
            sort($expectedclasses);
            $actualclasses = $actualgroups[$component];
            sort($actualclasses);
            $this->assertEquals(
                $expectedclasses,
                $actualclasses,
                "Events in category '{$component}' must match"
            );
        }

        // Property 4b: Categories must be sorted alphabetically.
        $categorynames = array_map(function ($cat) {
            return $this->reverse_format_category_name($cat->name);
        }, $data->categories);

        $sortednames = $categorynames;
        sort($sortednames);

        $this->assertEquals(
            $sortednames,
            $categorynames,
            "Categories must be sorted alphabetically"
        );
    }

    /**
     * **Feature: mustache-templates-refactor, Property 5: Event selector selection state preservation**
     *
     * *For any* list of events and any set of selected event class names,
     * the event_selector renderable SHALL mark exactly those events as
     * checked in the output context.
     *
     * **Validates: Requirements 2.5**
     *
     * @dataProvider event_configuration_provider
     * @param int $seed Random seed for test data generation
     */
    public function test_property_selection_state_preservation(int $seed): void {
        $this->resetAfterTest(true);

        // Generate test data.
        $events = $this->generate_events($seed);
        $selected = $this->generate_selection($events, $seed + 1000);
        $selectedmap = array_flip($selected);

        // Create the renderable.
        $selector = new event_selector(
            'test_input_id',
            's_local_mc_plugin_monitored_events',
            $events,
            $selected
        );

        // Create a mock renderer.
        $page = new \moodle_page();
        $renderer = new \renderer_base($page, RENDERER_TARGET_GENERAL);

        // Export the template data.
        $data = $selector->export_for_template($renderer);

        // Property 5: Exactly the selected events must be marked as checked.
        $checkedcount = 0;
        $uncheckedcount = 0;

        foreach ($data->categories as $category) {
            foreach ($category->events as $event) {
                $shouldbechecked = isset($selectedmap[$event->classname]);

                if ($shouldbechecked) {
                    $this->assertTrue(
                        $event->checked,
                        "Event '{$event->classname}' should be checked"
                    );
                    $checkedcount++;
                } else {
                    $this->assertFalse(
                        $event->checked,
                        "Event '{$event->classname}' should not be checked"
                    );
                    $uncheckedcount++;
                }
            }
        }

        // Verify the count of checked events matches the selection.
        $this->assertEquals(
            count($selected),
            $checkedcount,
            "Number of checked events must match selection count"
        );
    }

    /**
     * Test hascategories flag is set correctly.
     */
    public function test_hascategories_flag(): void {
        $this->resetAfterTest(true);

        $page = new \moodle_page();
        $renderer = new \renderer_base($page, RENDERER_TARGET_GENERAL);

        // Test with events.
        $events = [
            ['class' => '\\core\\event\\user_created', 'name' => 'User created', 'component' => 'core'],
        ];
        $selector = new event_selector('id', 'name', $events, []);
        $data = $selector->export_for_template($renderer);
        $this->assertTrue($data->hascategories);

        // Test without events.
        $selector = new event_selector('id', 'name', [], []);
        $data = $selector->export_for_template($renderer);
        $this->assertFalse($data->hascategories);
    }

    /**
     * Test currentvalue contains comma-separated selected events.
     */
    public function test_currentvalue_format(): void {
        $this->resetAfterTest(true);

        $events = [
            ['class' => '\\core\\event\\user_created', 'name' => 'User created', 'component' => 'core'],
            ['class' => '\\core\\event\\user_deleted', 'name' => 'User deleted', 'component' => 'core'],
            ['class' => '\\mod_assign\\event\\submission_created', 'name' => 'Submission created',
                'component' => 'mod_assign'],
        ];
        $selected = ['\\core\\event\\user_created', '\\mod_assign\\event\\submission_created'];

        $selector = new event_selector('id', 'name', $events, $selected);

        $page = new \moodle_page();
        $renderer = new \renderer_base($page, RENDERER_TARGET_GENERAL);

        $data = $selector->export_for_template($renderer);

        // Verify currentvalue contains the selected events.
        $currentvalueparts = explode(',', $data->currentvalue);
        sort($currentvalueparts);
        sort($selected);
        $this->assertEquals($selected, $currentvalueparts);
    }

    /**
     * Reverse the category name formatting to get the component name.
     *
     * @param string $name Formatted category name
     * @return string Component name
     */
    private function reverse_format_category_name(string $name): string {
        if ($name === 'Core') {
            return 'core';
        }
        return str_replace(' ', '_', strtolower($name));
    }
}
