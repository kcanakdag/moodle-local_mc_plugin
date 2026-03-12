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
 * Manual cleanup utilities for local action execution claims.
 *
 * Provides safe query/delete operations for stale pending idempotency claims.
 * This is intentionally manual so active in-flight claims are never auto-reclaimed.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mc_plugin\local\actions;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- direct access fatals before Moodle bootstrap.
defined('MOODLE_INTERNAL') || die();

/**
 * Cleanup service for pending execution claims.
 *
 * @package    local_mc_plugin
 * @copyright  2025 Kerem Can Akdag
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class execution_claim_cleanup {
    /** @var string Sentinel value used by action_executor while claim is in progress. */
    private const CLAIM_PENDING_SENTINEL = action_executor::CLAIM_PENDING_SENTINEL;

    /**
     * Find pending claims older than a threshold.
     *
     * @param int $olderthanseconds Only include records older than this age in seconds.
     * @param string|null $actiontype Optional action_type filter.
     * @param string|null $fingerprint Optional exact event fingerprint filter.
     * @return array<int, \stdClass> Records keyed by id.
     */
    public static function find_pending_claims(
        int $olderthanseconds = 86400,
        ?string $actiontype = null,
        ?string $fingerprint = null
    ): array {
        global $DB;

        $conditions = ['result = :pending'];
        $params = ['pending' => self::CLAIM_PENDING_SENTINEL];

        if ($olderthanseconds > 0) {
            $conditions[] = 'executed_at < :cutoff';
            $params['cutoff'] = time() - $olderthanseconds;
        }

        if (!empty($actiontype)) {
            $conditions[] = 'action_type = :actiontype';
            $params['actiontype'] = $actiontype;
        }

        if (!empty($fingerprint)) {
            $conditions[] = 'event_fingerprint = :fingerprint';
            $params['fingerprint'] = $fingerprint;
        }

        $sql = implode(' AND ', $conditions);
        return $DB->get_records_select(
            'local_mc_plugin_executions',
            $sql,
            $params,
            'executed_at ASC, id ASC',
            'id, action_type, event_fingerprint, user_id, target_id, result, executed_at'
        );
    }

    /**
     * Delete execution claims by id.
     *
     * @param int[] $claimids Claim record ids.
     * @return int Number of valid IDs submitted for deletion.
     */
    public static function delete_claims(array $claimids): int {
        global $DB;

        $validids = array_filter(array_map('intval', $claimids), function ($id) {
            return $id > 0;
        });

        if (empty($validids)) {
            return 0;
        }

        [$insql, $params] = $DB->get_in_or_equal($validids, SQL_PARAMS_NAMED, 'id');
        $DB->delete_records_select('local_mc_plugin_executions', "id $insql", $params);

        return count($validids);
    }

    /**
     * List and optionally delete pending claims.
     *
     * @param int $olderthanseconds Only include records older than this age in seconds.
     * @param bool $delete Whether to delete the matched claims.
     * @param string|null $actiontype Optional action_type filter.
     * @param string|null $fingerprint Optional exact event fingerprint filter.
     * @return array{
     *   total:int,
     *   deleted:int,
     *   claims:array<int,\stdClass>
     * } 'deleted' is the count of valid IDs submitted for deletion (not confirmed rows affected).
     */
    public static function cleanup_pending_claims(
        int $olderthanseconds = 86400,
        bool $delete = false,
        ?string $actiontype = null,
        ?string $fingerprint = null
    ): array {
        $claims = self::find_pending_claims($olderthanseconds, $actiontype, $fingerprint);
        $deleted = 0;

        if ($delete && !empty($claims)) {
            $deleted = self::delete_claims(array_keys($claims));
        }

        return [
            'total' => count($claims),
            'deleted' => $deleted,
            'claims' => array_values($claims),
        ];
    }
}
