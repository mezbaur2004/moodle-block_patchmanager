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

namespace block_patchmanager;

use block_patchmanager\local\statuscache;

/**
 * Tests that the block's cached status snapshot is invalidated on every
 * engine state change, so it cannot drift from the Patch Manager page's
 * fresh, on-demand status.
 *
 * Before this callback existed, apply/reapply/restore/verify/acknowledge and
 * the scheduled/CLI state check all called local_patchmanager\api::notify_packs(),
 * but nothing purged block_patchmanager's five-minute status cache - so right
 * after an admin changed a customisation's state on the Patch Manager page,
 * the Dashboard block kept showing the pre-change snapshot until its TTL
 * happened to expire.
 *
 * @package    block_patchmanager
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::block_patchmanager_patchmanager_state_changed
 * @covers     \block_patchmanager\local\statuscache
 */
final class lib_test extends \advanced_testcase {

    /**
     * The callback is discoverable the way local_patchmanager\api::notify_packs()
     * looks it up: as <frankenstyle>_patchmanager_state_changed() in lib.php.
     *
     * @return void
     */
    public function test_callback_is_registered_for_notify_packs(): void {
        $this->resetAfterTest();

        $functions = get_plugins_with_function('patchmanager_state_changed', 'lib.php');

        $found = false;
        foreach ($functions as $plugins) {
            foreach ($plugins as $functionname) {
                if ($functionname === 'block_patchmanager_patchmanager_state_changed') {
                    $found = true;
                }
            }
        }

        $this->assertTrue($found,
                'block_patchmanager must implement patchmanager_state_changed() so ' .
                'api::notify_packs() purges its status cache on every state change');
    }

    /**
     * Calling the callback drops whatever snapshot is currently cached, so the
     * next read is rebuilt from the engine rather than returned stale.
     *
     * @return void
     */
    public function test_callback_purges_the_status_cache(): void {
        $this->resetAfterTest();

        // Seed the cache with an obviously stale, fabricated snapshot - stands
        // in for "the Dashboard was rendered before a patch was applied".
        $stale = [
            'generated' => 1,
            'available' => true,
            'patches' => ['local_zoomcustom:001-period-grading' => ['state' => 'not_applied']],
        ];
        \cache::make('block_patchmanager', 'status')->set(statuscache::KEY, $stale);

        $this->assertSame($stale, statuscache::get(), 'the stale snapshot must actually be cached first');

        block_patchmanager_patchmanager_state_changed([]);

        $fresh = statuscache::get();
        $this->assertNotSame(1, $fresh['generated'],
                'after the callback runs, the next read must rebuild rather than return the stale snapshot');
    }
}
