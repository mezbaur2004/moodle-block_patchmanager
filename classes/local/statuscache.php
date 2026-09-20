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

namespace block_patchmanager\local;

/**
 * A short lived, site-level snapshot of the engine's status summary.
 *
 * This class computes nothing. It asks local_patchmanager for the answer and
 * flattens it into scalars so the Dashboard does not repeat the disk and
 * database work that api::build_status() performs.
 *
 * Only site-level facts are stored. Nothing here depends on the viewing user,
 * so one cached copy is correct for everyone; the per-user decision about which
 * actions to offer is made fresh on every render, never cached.
 *
 * @package    block_patchmanager
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class statuscache {

    /** @var string The single cache key; the snapshot covers every patch at once. */
    public const KEY = 'summary';

    /**
     * The current snapshot, computing it only when the cache has no fresh copy.
     *
     * @return array snapshot with 'generated' and 'patches'
     */
    public static function get(): array {
        $cache = \cache::make('block_patchmanager', 'status');

        $snapshot = $cache->get(self::KEY);
        if (is_array($snapshot) && isset($snapshot['patches'])) {
            return $snapshot;
        }

        $snapshot = self::build();
        $cache->set(self::KEY, $snapshot);

        return $snapshot;
    }

    /**
     * Drop the snapshot so the next read recomputes it.
     *
     * @return void
     */
    public static function purge(): void {
        \cache::make('block_patchmanager', 'status')->delete(self::KEY);
    }

    /**
     * Ask the engine for every status and flatten it.
     *
     * @return array
     */
    public static function build(): array {
        $snapshot = [
            'generated' => time(),
            'available' => false,
            'patches' => [],
        ];

        if (!class_exists('\local_patchmanager\api')) {
            return $snapshot;
        }

        $snapshot['available'] = true;

        try {
            $statuses = \local_patchmanager\api::get_statuses();
        } catch (\Throwable $e) {
            // A broken pack definition must not take the Dashboard down with it.
            debugging('block_patchmanager: could not read patch status: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $snapshot['available'] = false;
            return $snapshot;
        }

        foreach ($statuses as $key => $status) {
            $snapshot['patches'][$key] = self::flatten($key, $status);
        }

        return $snapshot;
    }

    /**
     * Reduce one status object to the scalars the block displays.
     *
     * The can_* values come straight from the engine's own status rules. The
     * block never decides for itself whether an action is legal.
     *
     * @param string $key
     * @param \local_patchmanager\status $status
     * @return array
     */
    protected static function flatten(string $key, \local_patchmanager\status $status): array {
        $definition = $status->definition;
        $version = $status->componentversion;

        return [
            'key' => $key,
            'pack' => $definition->pack,
            'patchid' => $definition->id,
            'name' => (string) $definition->name,
            'state' => $status->state,
            'statelabel' => $status->state_label(),
            'severity' => $status->severity(),
            'required' => (bool) $status->required,
            'iscurrent' => $status->is_current(),

            'targetcomponent' => (string) $definition->component,
            'targetinstalled' => (bool) ($version->installed ?? false),
            'targetversiondisk' => $version->versiondisk ?? null,
            'targetversiondb' => $version->versiondb ?? null,
            'targetupgradepending' => (bool) ($version->upgradepending ?? false),

            'verified' => (bool) $status->verified,
            'verifiedsource' => $status->verifiedsource,
            'verificationstale' => (bool) $status->verificationstale,
            'acknowledged' => $status->acknowledgement !== null,

            'lastapplytime' => isset($status->lastapply->timecreated)
                ? (int) $status->lastapply->timecreated : null,
            'changedsinceapply' => (bool) $status->changedsinceapply,

            // Engine rules, copied verbatim. Permission is applied separately.
            'canapply' => $status->can_apply(),
            'canreapply' => $status->can_reapply(),
            'canrestore' => $status->can_restore(),
            'canverify' => $status->can_verify(),

            'reasons' => array_values(array_map('strval', $status->reasons)),
            'blockedby' => array_values(array_map('strval', $status->blockedby)),

            'heartbeat' => self::heartbeat_for($definition->pack),
        ];
    }

    /**
     * The pack's own heartbeat, when that pack publishes one.
     *
     * The engine has no heartbeat of its own, because whether executing code
     * can be observed is a question only a pack can answer. Rather than naming
     * any particular pack here, this looks for the conventional
     * <pack>\heartbeat::info() on whichever packs are registered, and shows
     * nothing when a pack does not provide it.
     *
     * @param string $pack Frankenstyle name of the pack that owns the patch.
     * @return array|null
     */
    protected static function heartbeat_for(string $pack): ?array {
        $class = '\\' . $pack . '\\heartbeat';
        if (!class_exists($class) || !method_exists($class, 'info')) {
            return null;
        }

        try {
            $info = $class::info();
        } catch (\Throwable $e) {
            debugging('block_patchmanager: could not read heartbeat for ' . $pack . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER);
            return null;
        }

        if (!is_object($info) || empty($info->time)) {
            return ['seen' => false, 'time' => null, 'context' => null];
        }

        return [
            'seen' => true,
            'time' => (int) $info->time,
            'context' => isset($info->context) ? (string) $info->context : null,
        ];
    }
}
