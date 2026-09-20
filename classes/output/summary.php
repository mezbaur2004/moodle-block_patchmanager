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

namespace block_patchmanager\output;

use renderer_base;

/**
 * The block's status summary, prepared for its template.
 *
 * Every action this exposes is a link to the Patch Manager page, which keeps
 * its own confirmation, POST, sesskey and permission gates. The block performs
 * no write of any kind.
 *
 * @package    block_patchmanager
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class summary implements \renderable, \templatable {

    /** @var array The cached snapshot from the engine. */
    protected array $snapshot;

    /** @var bool[] Action name => whether this user may be offered it. */
    protected array $permissions;

    /**
     * Constructor.
     *
     * @param array $snapshot snapshot from statuscache::get()
     * @param bool[] $permissions action => local_patchmanager api::can_manage_action($action, true)
     */
    public function __construct(array $snapshot, array $permissions) {
        $this->snapshot = $snapshot;
        $this->permissions = $permissions;
    }

    /**
     * Whether one action may be offered to this user.
     *
     * Defaults to refusing, so an action the caller did not resolve is never
     * shown.
     *
     * @param string $action
     * @return bool
     */
    protected function allowed(string $action): bool {
        return !empty($this->permissions[$action]);
    }

    /**
     * Export for the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $manageurl = new \moodle_url('/local/patchmanager/index.php');

        $data = [
            'available' => !empty($this->snapshot['available']),
            'manageurl' => $manageurl->out(false),
            'checkurl' => (new \moodle_url('/local/patchmanager/index.php', [
                'action' => 'check',
                'sesskey' => sesskey(),
            ]))->out(false),
            'canmanage' => (bool) array_filter($this->permissions),
            'lastcheck' => null,
            'patches' => [],
            'haspatches' => false,
            'overall' => null,
        ];

        if (!empty($this->snapshot['generated'])) {
            $data['lastcheck'] = userdate((int) $this->snapshot['generated']);
        }

        foreach ($this->snapshot['patches'] ?? [] as $patch) {
            $data['patches'][] = $this->export_patch($patch);
        }

        $data['haspatches'] = !empty($data['patches']);
        $data['overall'] = $this->overall($data['patches']);

        return $data;
    }

    /**
     * One patch row.
     *
     * @param array $patch
     * @return array
     */
    protected function export_patch(array $patch): array {
        $row = [
            'key' => $patch['key'],
            'name' => $patch['name'],
            'state' => $patch['state'],
            'statelabel' => $patch['statelabel'],
            'severity' => $patch['severity'],
            'badgeclass' => $this->badge_class($patch['severity']),
            'required' => !empty($patch['required']),
            'targetcomponent' => $patch['targetcomponent'],
            'targetversion' => $this->target_version_label($patch),
            'verificationlabel' => $this->verification_label($patch),
            'heartbeatlabel' => $this->heartbeat_label($patch),
            'reasons' => $patch['reasons'],
            'hasreasons' => !empty($patch['reasons']),
            'actions' => [],
            'hasactions' => false,
        ];

        // An action is offered only when the engine's own can_*() rule allows it
        // for this patch AND this user is permitted that specific action. Both
        // must hold, and permission is asked per action because applying needs
        // the web-apply switch while verifying does not.
        $candidates = [
            'apply' => !empty($patch['canapply']),
            'reapply' => !empty($patch['canreapply']),
            'restore' => !empty($patch['canrestore']),
            'verify' => !empty($patch['canverify']),
        ];

        foreach ($candidates as $action => $allowed) {
            if (!$allowed || !$this->allowed($action)) {
                continue;
            }

            $row['actions'][] = [
                'action' => $action,
                'label' => get_string('action_' . $action, 'block_patchmanager'),
                'url' => (new \moodle_url('/local/patchmanager/index.php', [
                    'action' => $action,
                    'pack' => $patch['pack'],
                    'patch' => $patch['patchid'],
                    'sesskey' => sesskey(),
                ]))->out(false),
            ];
        }

        $row['hasactions'] = !empty($row['actions']);

        return $row;
    }

    /**
     * The worst severity across all patches, which is what the block leads with.
     *
     * @param array $patches
     * @return array|null
     */
    protected function overall(array $patches): ?array {
        if (empty($patches)) {
            return null;
        }

        $rank = ['ok' => 0, 'warning' => 1, 'error' => 2];
        $worst = 'ok';
        foreach ($patches as $patch) {
            if (($rank[$patch['severity']] ?? 2) > ($rank[$worst] ?? 0)) {
                $worst = $patch['severity'];
            }
        }

        return [
            'severity' => $worst,
            'badgeclass' => $this->badge_class($worst),
            'label' => get_string('overall_' . $worst, 'block_patchmanager'),
        ];
    }

    /**
     * Bootstrap badge class for a severity.
     *
     * @param string $severity
     * @return string
     */
    protected function badge_class(string $severity): string {
        switch ($severity) {
            case 'ok':
                return 'badge bg-success text-white';
            case 'warning':
                return 'badge bg-warning text-dark';
            default:
                return 'badge bg-danger text-white';
        }
    }

    /**
     * Target component and version, as one readable string.
     *
     * @param array $patch
     * @return string
     */
    protected function target_version_label(array $patch): string {
        if (empty($patch['targetinstalled'])) {
            return get_string('targetnotinstalled', 'block_patchmanager');
        }

        $disk = $patch['targetversiondisk'];
        if ($disk === null) {
            return get_string('targetversionunknown', 'block_patchmanager');
        }

        if (!empty($patch['targetupgradepending'])) {
            return get_string('targetversionpending', 'block_patchmanager', (object) [
                'disk' => $disk,
                'db' => $patch['targetversiondb'],
            ]);
        }

        return (string) $disk;
    }

    /**
     * Verification state, as one readable string.
     *
     * @param array $patch
     * @return string
     */
    protected function verification_label(array $patch): string {
        if (!empty($patch['verificationstale'])) {
            return get_string('verificationstale', 'block_patchmanager');
        }

        if (empty($patch['verified'])) {
            return get_string('verificationnone', 'block_patchmanager');
        }

        if (!empty($patch['verifiedsource'])) {
            return get_string('verificationby', 'block_patchmanager', $patch['verifiedsource']);
        }

        return get_string('verificationyes', 'block_patchmanager');
    }

    /**
     * Heartbeat state, or an empty string when the pack publishes none.
     *
     * @param array $patch
     * @return string
     */
    protected function heartbeat_label(array $patch): string {
        $heartbeat = $patch['heartbeat'] ?? null;
        if ($heartbeat === null) {
            return '';
        }

        if (empty($heartbeat['seen']) || empty($heartbeat['time'])) {
            return get_string('heartbeatnone', 'block_patchmanager');
        }

        return get_string('heartbeatseen', 'block_patchmanager', userdate((int) $heartbeat['time']));
    }
}
