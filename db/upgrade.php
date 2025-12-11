<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_mc_plugin_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // If upgrading to the stateless MVP version, drop all old tables.
    if ($oldversion < 2025112801) {
        $tables = [
            'local_mc_plugin_map',
            'local_mc_plugin_job',
            'local_mc_plugin_sync',
            'local_mc_plugin_schema_cache',
            'local_mc_plugin_entity_map',
            'local_mc_plugin_template'
        ];

        foreach ($tables as $tablename) {
            $table = new xmldb_table($tablename);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
        }

        upgrade_plugin_savepoint(true, 2025112801, 'local', 'mc_plugin');
    }

    return true;
}
