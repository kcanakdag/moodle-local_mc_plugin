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
 * CLI tool to inspect and clean orphaned pending local-action execution claims.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_mc_plugin\local\actions\execution_claim_cleanup;

$help = <<<EOF
MoodleConnect pending claim cleanup.

Lists and optionally deletes stale '__pending__' rows from local_mc_plugin_executions.
This is a manual recovery command for orphaned claims after interrupted execution.

Options:
--list                 List matching pending claims.
--delete               Delete matching pending claims (requires --yes).
--yes                  Confirm delete operation.
--olderthan=SECONDS    Only include claims older than this age. Default: 86400 (24h).
--actiontype=TYPE      Optional filter by action type.
--fingerprint=HASH     Optional exact filter by event fingerprint.
-h, --help             Show this help.

Examples:
php local/mc_plugin/cli/cleanup_pending_claims.php --list
php local/mc_plugin/cli/cleanup_pending_claims.php --list --olderthan=172800
php local/mc_plugin/cli/cleanup_pending_claims.php --delete --yes --olderthan=86400 --actiontype=send_message

EOF;

$longopts = [
    'help' => false,
    'list' => false,
    'delete' => false,
    'yes' => false,
    'olderthan' => 86400,
    'actiontype' => '',
    'fingerprint' => '',
];
$shortopts = [
    'h' => 'help',
];

[$options, $unrecognized] = cli_get_params($longopts, $shortopts);
if (!empty($unrecognized)) {
    cli_error('Unknown options: ' . implode(', ', $unrecognized));
}

if (!empty($options['help'])) {
    echo $help;
    exit(0);
}

$modecount = (!empty($options['list']) ? 1 : 0) + (!empty($options['delete']) ? 1 : 0);
if ($modecount !== 1) {
    cli_error('Specify exactly one mode: --list or --delete' . PHP_EOL . $help);
}

$olderthanseconds = (int) ($options['olderthan'] ?? 86400);
if ($olderthanseconds < 0) {
    cli_error('--olderthan must be >= 0');
}

$actiontype = trim((string) ($options['actiontype'] ?? ''));
$fingerprint = trim((string) ($options['fingerprint'] ?? ''));

if (!empty($options['delete']) && empty($options['yes'])) {
    cli_error('Delete mode requires --yes confirmation.');
}

$result = execution_claim_cleanup::cleanup_pending_claims(
    $olderthanseconds,
    !empty($options['delete']),
    $actiontype !== '' ? $actiontype : null,
    $fingerprint !== '' ? $fingerprint : null
);

cli_writeln('Matched pending claims: ' . $result['total']);
if ($result['total'] > 0) {
    $now = time();
    foreach ($result['claims'] as $claim) {
        $age = max(0, $now - (int) $claim->executed_at);
        cli_writeln(
            sprintf(
                'id=%d action=%s user=%s target=%s age=%ss fingerprint=%s',
                (int) $claim->id,
                (string) $claim->action_type,
                $claim->user_id === null ? 'null' : (string) (int) $claim->user_id,
                $claim->target_id === null ? 'null' : (string) (int) $claim->target_id,
                $age,
                (string) $claim->event_fingerprint
            )
        );
    }
}

if (!empty($options['delete'])) {
    cli_writeln('Submitted for deletion: ' . (int) $result['deleted']);
}
