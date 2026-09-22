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
 * Language strings for block_patchmanager.
 *
 * @package    block_patchmanager
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Patch Manager';

// Capabilities.
$string['patchmanager:addinstance'] = 'Add a new Patch Manager block';
$string['patchmanager:myaddinstance'] = 'Add a new Patch Manager block to the Dashboard';

// Overall status.
$string['overall_ok'] = 'All customisations active';
$string['overall_warning'] = 'Attention needed';
$string['overall_error'] = 'Action required';

// Field labels.
$string['target'] = 'Target';
$string['verification'] = 'Verification';
$string['heartbeat'] = 'Heartbeat';
$string['required'] = 'Required';
$string['lastcheck'] = 'State as of {$a}';

// Verification wording.
$string['verificationyes'] = 'Verified';
$string['verificationby'] = 'Verified ({$a})';
$string['verificationnone'] = 'Not verified';
$string['verificationstale'] = 'Verification out of date';

// Heartbeat wording.
$string['heartbeatseen'] = 'Last seen {$a}';
$string['heartbeatnone'] = 'Not yet observed';

// Target version wording.
$string['targetnotinstalled'] = 'not installed';
$string['targetversionunknown'] = 'version unknown';
$string['targetversionpending'] = '{$a->disk} (upgrade pending, database at {$a->db})';

// Actions. These link to the Patch Manager page, which confirms them, except
// when the site keeps code changes CLI-only (see clionly / clihint below).
$string['action_apply'] = 'Apply';
$string['action_reapply'] = 'Reapply';
$string['action_restore'] = 'Remove patch';
$string['action_verify'] = 'Verify';
$string['manage'] = 'Manage';
$string['check'] = 'Check now';

// Shown instead of a clickable action when $CFG->local_patchmanager_allowwebapply
// is off: the button is disabled and this explains what to run instead.
$string['clionly'] = 'Browser changes are disabled on this site. Run this instead:';
$string['clihint'] = 'cd {$a->dirroot}
sudo -u www-data php local/patchmanager/cli/{$a->script}.php --patch={$a->key}';
$string['clihint_nodirroot'] = 'Run from the Moodle root directory:
sudo -u www-data php local/patchmanager/cli/{$a->script}.php --patch={$a->key}';

// Empty and error states.
$string['nopatches'] = 'No customisations are registered.';
$string['enginenotavailable'] = 'Patch status is unavailable.';

// Privacy.
$string['privacy:metadata'] = 'The Patch Manager block displays site-level customisation status and stores no personal data.';
