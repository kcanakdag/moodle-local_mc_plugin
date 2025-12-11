<?php
defined('MOODLE_INTERNAL') || die();

// Register the observer for common Moodle events.
// We also register for \core\event\base to attempt catching everything,
// allowing the observer to filter based on settings.
$observers = [
    // Wildcard observer - catches ALL Moodle events (Moodle 3.1+)
    // The observer filters based on user-selected events in settings
    [
        'eventname' => '*',
        'callback'  => '\local_mc_plugin\observer::handle_event',
    ],
];
