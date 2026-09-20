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
 * Cache definitions for block_patchmanager.
 *
 * @package    block_patchmanager
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [

    // A short lived snapshot of the engine's status summary.
    //
    // api::build_status() reads every target file from disk, probes writability
    // and OPcache, and queries the audit tables, for each registered patch.
    // That is appropriate for the Patch Manager page and for the scheduled
    // check, but not for every Dashboard view by every manager. The snapshot
    // holds only site-level facts, never anything user-specific, so one cached
    // copy is correct for all viewers.
    //
    // A live check remains available through the explicit Check action.
    'status' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'staticacceleration' => true,
        'staticaccelerationsize' => 1,
        'ttl' => 300,
    ],
];
