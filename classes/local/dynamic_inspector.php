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
 * Dynamic inspector service for extracting available fields from Moodle events.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Service class for dynamically inspecting events and extracting available data fields.
 */
class dynamic_inspector {

    /**
     * Get schema for a single event class (for schema sync).
     */
    public function get_event_schema(string $eventclass): array {
        $fields = $this->get_mock_fields($eventclass);
        $flatfields = [];
        
        foreach ($fields as $category => $categoryfields) {
            foreach ($categoryfields as $fieldname => $fieldinfo) {
                $flatfields[] = $category . '.' . $fieldname;
            }
        }
        
        return [
            'event_type' => $eventclass,
            'name' => event_discovery::get_friendly_name($eventclass),
            'component' => $this->extract_component($eventclass),
            'fields' => $flatfields,
        ];
    }

    /**
     * Get schemas for multiple event classes.
     */
    public function get_event_schemas(array $eventclasses): array {
        $schemas = [];
        foreach ($eventclasses as $eventclass) {
            $eventclass = trim($eventclass);
            if (!empty($eventclass)) {
                $schemas[] = $this->get_event_schema($eventclass);
            }
        }
        return $schemas;
    }

    /**
     * Extract data from a live event instance.
     */
    public function extract_data(\core\event\base $event): array {
        return $this->extract_fields_from_event($event);
    }

    /**
     * Get sample data from recent events of the specified class.
     */
    public function get_sample_data(string $eventclass): array {
        global $DB;

        try {
            $logevent = $DB->get_record_sql(
                "SELECT * FROM {logstore_standard_log} 
                 WHERE eventname = :eventname 
                 ORDER BY timecreated DESC",
                ['eventname' => $eventclass],
                IGNORE_MULTIPLE
            );

            if ($logevent) {
                return $this->extract_fields_from_log($logevent, $eventclass);
            }
        } catch (\Exception $e) {
            // Log table might not exist or query failed
        }

        return $this->get_mock_fields($eventclass);
    }

    private function extract_fields_from_event(\core\event\base $event): array {
        global $DB;

        $fields = [
            'user' => [],
            'course' => [],
            'object' => [],
            'event' => [],
        ];

        $useridtoload = !empty($event->relateduserid) ? $event->relateduserid : $event->userid;
        
        if (!empty($useridtoload)) {
            try {
                $user = \core_user::get_user($useridtoload);
                if ($user) {
                    $fields['user'] = [
                        'id' => ['value' => $user->id, 'type' => 'int', 'label' => 'User ID'],
                        'email' => ['value' => $user->email, 'type' => 'string', 'label' => 'Email'],
                        'firstname' => ['value' => $user->firstname, 'type' => 'string', 'label' => 'First Name'],
                        'lastname' => ['value' => $user->lastname, 'type' => 'string', 'label' => 'Last Name'],
                        'username' => ['value' => $user->username, 'type' => 'string', 'label' => 'Username'],
                        'idnumber' => ['value' => $user->idnumber ?? '', 'type' => 'string', 'label' => 'ID Number'],
                    ];
                }
            } catch (\Exception $e) {
                // User not found
            }
        }

        if (!empty($event->courseid) && $event->courseid != SITEID) {
            try {
                $course = get_course($event->courseid);
                if ($course) {
                    $fields['course'] = [
                        'id' => ['value' => $course->id, 'type' => 'int', 'label' => 'Course ID'],
                        'fullname' => ['value' => $course->fullname, 'type' => 'string', 'label' => 'Course Name'],
                        'shortname' => ['value' => $course->shortname, 'type' => 'string', 'label' => 'Short Name'],
                        'idnumber' => ['value' => $course->idnumber ?? '', 'type' => 'string', 'label' => 'Course ID Number'],
                        'startdate' => ['value' => $course->startdate, 'type' => 'datetime', 'label' => 'Start Date'],
                    ];
                }
            } catch (\Exception $e) {
                // Course not found
            }
        }

        if (!empty($event->objecttable) && !empty($event->objectid)) {
            try {
                $object = $event->get_record_snapshot($event->objecttable, $event->objectid);
                
                if (!$object) {
                    $object = $DB->get_record($event->objecttable, ['id' => $event->objectid]);
                }
                
                if ($object) {
                    foreach ($object as $key => $value) {
                        $fields['object'][$key] = [
                            'value' => $value,
                            'type' => $this->detect_type($value),
                            'label' => $this->format_label($key),
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Object not found
            }
        }

        $fields['event'] = [
            'type' => ['value' => $event->eventname, 'type' => 'string', 'label' => 'Event Type'],
            'timecreated' => ['value' => $event->timecreated, 'type' => 'datetime', 'label' => 'Event Time'],
            'component' => ['value' => $event->component, 'type' => 'string', 'label' => 'Component'],
        ];

        return $fields;
    }

    private function extract_fields_from_log(\stdClass $logevent, string $eventclass): array {
        global $DB;

        $fields = [
            'user' => [],
            'course' => [],
            'object' => [],
            'event' => [],
        ];

        if (!empty($logevent->userid)) {
            try {
                $user = \core_user::get_user($logevent->userid);
                if ($user) {
                    $fields['user'] = [
                        'id' => ['value' => $user->id, 'type' => 'int', 'label' => 'User ID'],
                        'email' => ['value' => $user->email, 'type' => 'string', 'label' => 'Email'],
                        'firstname' => ['value' => $user->firstname, 'type' => 'string', 'label' => 'First Name'],
                        'lastname' => ['value' => $user->lastname, 'type' => 'string', 'label' => 'Last Name'],
                        'username' => ['value' => $user->username, 'type' => 'string', 'label' => 'Username'],
                        'idnumber' => ['value' => $user->idnumber ?? '', 'type' => 'string', 'label' => 'ID Number'],
                    ];
                }
            } catch (\Exception $e) {}
        }

        if (!empty($logevent->courseid) && $logevent->courseid != SITEID) {
            try {
                $course = get_course($logevent->courseid);
                if ($course) {
                    $fields['course'] = [
                        'id' => ['value' => $course->id, 'type' => 'int', 'label' => 'Course ID'],
                        'fullname' => ['value' => $course->fullname, 'type' => 'string', 'label' => 'Course Name'],
                        'shortname' => ['value' => $course->shortname, 'type' => 'string', 'label' => 'Short Name'],
                        'idnumber' => ['value' => $course->idnumber ?? '', 'type' => 'string', 'label' => 'Course ID Number'],
                        'startdate' => ['value' => $course->startdate, 'type' => 'datetime', 'label' => 'Start Date'],
                    ];
                }
            } catch (\Exception $e) {}
        }

        if (!empty($logevent->objecttable) && !empty($logevent->objectid)) {
            try {
                $object = $DB->get_record($logevent->objecttable, ['id' => $logevent->objectid]);
                if ($object) {
                    foreach ($object as $key => $value) {
                        $fields['object'][$key] = [
                            'value' => $value,
                            'type' => $this->detect_type($value),
                            'label' => $this->format_label($key),
                        ];
                    }
                } else {
                    $fields['object'] = $this->get_fields_from_table_schema($logevent->objecttable);
                }
            } catch (\Exception $e) {
                $fields['object'] = $this->get_fields_from_table_schema($logevent->objecttable);
            }
        } else if (!empty($logevent->objecttable)) {
            $fields['object'] = $this->get_fields_from_table_schema($logevent->objecttable);
        }

        $fields['event'] = [
            'type' => ['value' => $eventclass, 'type' => 'string', 'label' => 'Event Type'],
            'timecreated' => ['value' => $logevent->timecreated, 'type' => 'datetime', 'label' => 'Event Time'],
            'component' => ['value' => $this->extract_component($eventclass), 'type' => 'string', 'label' => 'Component'],
        ];

        return $fields;
    }

    private function get_mock_fields(string $eventclass): array {
        $objectfields = $this->get_object_fields_from_event_class($eventclass);

        return [
            'user' => [
                'id' => ['value' => null, 'type' => 'int', 'label' => 'User ID'],
                'email' => ['value' => null, 'type' => 'string', 'label' => 'Email'],
                'firstname' => ['value' => null, 'type' => 'string', 'label' => 'First Name'],
                'lastname' => ['value' => null, 'type' => 'string', 'label' => 'Last Name'],
                'username' => ['value' => null, 'type' => 'string', 'label' => 'Username'],
                'idnumber' => ['value' => null, 'type' => 'string', 'label' => 'ID Number'],
            ],
            'course' => [
                'id' => ['value' => null, 'type' => 'int', 'label' => 'Course ID'],
                'fullname' => ['value' => null, 'type' => 'string', 'label' => 'Course Name'],
                'shortname' => ['value' => null, 'type' => 'string', 'label' => 'Short Name'],
                'idnumber' => ['value' => null, 'type' => 'string', 'label' => 'Course ID Number'],
                'startdate' => ['value' => null, 'type' => 'datetime', 'label' => 'Start Date'],
            ],
            'object' => $objectfields,
            'event' => [
                'type' => ['value' => $eventclass, 'type' => 'string', 'label' => 'Event Type'],
                'timecreated' => ['value' => null, 'type' => 'datetime', 'label' => 'Event Time'],
                'component' => ['value' => $this->extract_component($eventclass), 'type' => 'string', 'label' => 'Component'],
            ],
        ];
    }

    private function get_object_fields_from_event_class(string $eventclass): array {
        $objecttable = $this->get_objecttable_from_class($eventclass);

        if (empty($objecttable)) {
            return [];
        }

        return $this->get_fields_from_table_schema($objecttable);
    }

    private function get_objecttable_from_class(string $eventclass): ?string {
        if (!class_exists($eventclass)) {
            return null;
        }

        try {
            $reflection = new \ReflectionClass($eventclass);

            if ($reflection->hasProperty('objecttable')) {
                $prop = $reflection->getProperty('objecttable');
                $prop->setAccessible(true);

                if ($prop->isStatic()) {
                    $value = $prop->getValue();
                    if (!empty($value)) {
                        return $value;
                    }
                }
            }

            if ($reflection->isSubclassOf('\core\event\base') && !$reflection->isAbstract()) {
                $dummydata = [
                    'context' => \context_system::instance(),
                    'objectid' => 1,
                    'userid' => 1,
                ];

                try {
                    $event = $eventclass::create($dummydata);
                    if (!empty($event->objecttable)) {
                        return $event->objecttable;
                    }
                } catch (\Exception $e) {
                } catch (\Error $e) {
                }
            }

            if ($reflection->hasMethod('get_objectid_mapping')) {
                $method = $reflection->getMethod('get_objectid_mapping');
                if ($method->isStatic()) {
                    try {
                        $mapping = $method->invoke(null);
                        if (is_array($mapping) && !empty($mapping['db'])) {
                            return $mapping['db'];
                        }
                    } catch (\Error $e) {
                    }
                }
            }

        } catch (\Exception $e) {
        }

        return null;
    }

    private function get_fields_from_table_schema(string $tablename): array {
        global $DB;

        $fields = [];

        try {
            $columns = $DB->get_columns($tablename);

            foreach ($columns as $colname => $column) {
                $fields[$colname] = [
                    'value' => null,
                    'type' => $this->map_db_type_to_field_type($column),
                    'label' => $this->format_label($colname),
                ];
            }
        } catch (\Exception $e) {
        }

        return $fields;
    }

    private function map_db_type_to_field_type($column): string {
        $type = strtolower($column->type ?? '');
        $name = strtolower($column->name ?? '');

        if (strpos($name, 'time') !== false || strpos($name, 'date') !== false) {
            return 'datetime';
        }

        if (strpos($type, 'int') !== false || $type === 'bigint') {
            return 'int';
        }

        if (strpos($type, 'float') !== false || strpos($type, 'double') !== false ||
            strpos($type, 'decimal') !== false || strpos($type, 'numeric') !== false) {
            return 'float';
        }

        if ($type === 'boolean' || $type === 'bool' || $type === 'tinyint') {
            return 'bool';
        }

        return 'string';
    }

    private function detect_type($value): string {
        if (is_null($value)) {
            return 'string';
        }

        if (is_bool($value)) {
            return 'bool';
        }

        if (is_int($value)) {
            if ($value > 1000000000 && $value < 2000000000) {
                return 'datetime';
            }
            return 'int';
        }

        if (is_float($value)) {
            return 'float';
        }

        return 'string';
    }

    private function format_label(string $key): string {
        $label = str_replace('_', ' ', $key);
        $label = ucwords($label);
        return $label;
    }

    private function extract_component(string $eventclass): string {
        $parts = explode('\\', $eventclass);
        if (count($parts) > 0) {
            return $parts[0];
        }
        return 'unknown';
    }

    public function get_nested_value(array $data, string $path) {
        $parts = explode('.', $path);

        if (count($parts) !== 2) {
            return null;
        }

        $category = $parts[0];
        $field = $parts[1];

        if (isset($data[$category][$field]['value'])) {
            return $data[$category][$field]['value'];
        }

        return null;
    }
}
