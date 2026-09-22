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
 * Callbacks for block_patchmanager.
 *
 * @package    block_patchmanager
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * React to a state change reported by the engine.
 *
 * local_patchmanager\api::notify_packs() calls this after apply, reapply,
 * restore, verify and acknowledge, and from the scheduled/CLI state check -
 * every path that can make the Patch Manager page's fresh, on-demand status
 * differ from what the Dashboard block last cached. Without this callback the
 * block's statuscache only refreshed on its own TTL (five minutes), so right
 * after an admin applied and verified a customisation on the Patch Manager
 * page, the block kept showing the pre-change snapshot - wrong state, wrong
 * verification, and a heartbeat frozen at whenever that snapshot was built.
 *
 * This only drops the cached snapshot; the next Dashboard render rebuilds it
 * from local_patchmanager\api::get_statuses(), the same call the Patch
 * Manager page itself uses, so the two stay in sync.
 *
 * @param \local_patchmanager\status[] $statuses unused: purging is state-independent
 * @return void
 */
function block_patchmanager_patchmanager_state_changed(array $statuses): void {
    \block_patchmanager\local\statuscache::purge();
}
