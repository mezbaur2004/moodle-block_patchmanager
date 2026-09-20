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
 * Version details for block_patchmanager.
 *
 * @package    block_patchmanager
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_patchmanager';
$plugin->version = 2026092001;
$plugin->requires = 2024100700; // Moodle 4.5.
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = '1.0.1';

// The block is a view over the engine. It carries no patch logic of its own and
// is useless without it, so it requires the version that exposes
// api::can_manage_action() and api::MANAGED_ACTIONS.
$plugin->dependencies = [
    'local_patchmanager' => 2026092003,
];
