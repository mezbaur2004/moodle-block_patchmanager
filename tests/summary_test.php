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

use block_patchmanager\output\summary;

/**
 * Tests for the Patch Manager block's summary rendering and gating.
 *
 * @package    block_patchmanager
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_patchmanager\output\summary
 */
final class summary_test extends \advanced_testcase {

    /**
     * A snapshot row in a chosen condition.
     *
     * @param array $overrides
     * @return array
     */
    private function snapshot(array $overrides = []): array {
        $patch = array_merge([
            'key' => 'local_zoomcustom:001-period-grading',
            'pack' => 'local_zoomcustom',
            'patchid' => '001-period-grading',
            'name' => 'Period grading',
            'state' => 'applied',
            'statelabel' => 'Active',
            'severity' => 'ok',
            'required' => true,
            'iscurrent' => true,
            'targetcomponent' => 'mod_zoom',
            'targetinstalled' => true,
            'targetversiondisk' => 2026082400,
            'targetversiondb' => 2026082400,
            'targetupgradepending' => false,
            'verified' => true,
            'verifiedsource' => null,
            'verificationstale' => false,
            'acknowledged' => false,
            'lastapplytime' => null,
            'changedsinceapply' => false,
            'canapply' => false,
            'canreapply' => false,
            'canrestore' => false,
            'canverify' => false,
            'reasons' => [],
            'blockedby' => [],
            'heartbeat' => null,
        ], $overrides);

        return [
            'generated' => 1789623600,
            'available' => true,
            'patches' => [$patch['key'] => $patch],
        ];
    }

    /**
     * Export the summary through a real renderer.
     *
     * @param array $snapshot
     * @param bool|array $permissions true/false for every action, or a per-action map
     * @return array
     */
    private function export(array $snapshot, $permissions): array {
        global $PAGE;
        $renderable = new summary($snapshot, $this->permissions($permissions));
        return $renderable->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Expand a blanket true/false into the per-action map the block now passes.
     *
     * @param bool|array $permissions
     * @return bool[]
     */
    private function permissions($permissions): array {
        if (is_array($permissions)) {
            return $permissions;
        }

        $map = [];
        foreach (\local_patchmanager\api::MANAGED_ACTIONS as $action) {
            $map[$action] = (bool) $permissions;
        }

        return $map;
    }

    /**
     * The block shows the customisation name, target and verification state.
     *
     * @return void
     */
    public function test_status_is_displayed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = $this->export($this->snapshot(), false);

        $this->assertTrue($data['available']);
        $this->assertTrue($data['haspatches']);
        $this->assertCount(1, $data['patches']);

        $row = $data['patches'][0];
        $this->assertSame('Period grading', $row['name']);
        $this->assertSame('Active', $row['statelabel']);
        $this->assertSame('mod_zoom', $row['targetcomponent']);
        $this->assertSame('2026082400', $row['targetversion']);
        $this->assertSame(get_string('verificationyes', 'block_patchmanager'), $row['verificationlabel']);
        $this->assertTrue($row['required']);

        // Overall severity is reported, and the last check time is shown.
        $this->assertSame('ok', $data['overall']['severity']);
        $this->assertNotEmpty($data['lastcheck']);
    }

    /**
     * An unverified patch reports as such, and drives overall severity.
     *
     * @return void
     */
    public function test_unverified_is_reported(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = $this->export($this->snapshot([
            'verified' => false,
            'severity' => 'warning',
        ]), false);

        $this->assertSame(get_string('verificationnone', 'block_patchmanager'),
                $data['patches'][0]['verificationlabel']);
        $this->assertSame('warning', $data['overall']['severity']);
    }

    /**
     * A pending target upgrade is surfaced rather than shown as a bare version.
     *
     * @return void
     */
    public function test_pending_target_upgrade_is_reported(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = $this->export($this->snapshot([
            'targetupgradepending' => true,
            'targetversiondb' => 2026010100,
        ]), false);

        $this->assertStringContainsString('2026082400', $data['patches'][0]['targetversion']);
        $this->assertStringContainsString('2026010100', $data['patches'][0]['targetversion']);
    }

    /**
     * Heartbeat appears only when the pack publishes one.
     *
     * @return void
     */
    public function test_heartbeat_is_optional(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $none = $this->export($this->snapshot(), false);
        $this->assertSame('', $none['patches'][0]['heartbeatlabel']);

        $seen = $this->export($this->snapshot([
            'heartbeat' => ['seen' => true, 'time' => 1789623600, 'context' => 'period_durations'],
        ]), false);
        $this->assertNotEmpty($seen['patches'][0]['heartbeatlabel']);

        $unseen = $this->export($this->snapshot([
            'heartbeat' => ['seen' => false, 'time' => null, 'context' => null],
        ]), false);
        $this->assertSame(get_string('heartbeatnone', 'block_patchmanager'),
                $unseen['patches'][0]['heartbeatlabel']);
    }

    /**
     * Without manage permission no action link is offered, whatever the engine's
     * can_*() rules say.
     *
     * @return void
     */
    public function test_no_actions_without_manage_permission(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $snapshot = $this->snapshot([
            'canapply' => true,
            'canreapply' => true,
            'canrestore' => true,
            'canverify' => true,
        ]);

        $data = $this->export($snapshot, false);

        $this->assertFalse($data['canmanage']);
        $this->assertSame([], $data['patches'][0]['actions']);
        $this->assertFalse($data['patches'][0]['hasactions']);
    }

    /**
     * Action links follow the engine's can_*() rules exactly.
     *
     * @return void
     */
    public function test_actions_obey_engine_rules(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Only verify is allowed by the engine.
        $data = $this->export($this->snapshot(['canverify' => true]), true);
        $actions = array_column($data['patches'][0]['actions'], 'action');
        $this->assertSame(['verify'], $actions);

        // Only restore is allowed.
        $data = $this->export($this->snapshot(['canrestore' => true]), true);
        $actions = array_column($data['patches'][0]['actions'], 'action');
        $this->assertSame(['restore'], $actions);

        // Nothing is allowed.
        $data = $this->export($this->snapshot(), true);
        $this->assertSame([], $data['patches'][0]['actions']);

        // Everything is allowed; order is stable and complete.
        $data = $this->export($this->snapshot([
            'canapply' => true,
            'canreapply' => true,
            'canrestore' => true,
            'canverify' => true,
        ]), true);
        $actions = array_column($data['patches'][0]['actions'], 'action');
        $this->assertSame(['apply', 'reapply', 'restore', 'verify'], $actions);
    }

    /**
     * Every action link points at the Patch Manager page and carries a sesskey,
     * so it lands on that page's confirmation step rather than doing anything.
     *
     * @return void
     */
    public function test_action_links_target_the_confirmation_page(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = $this->export($this->snapshot([
            'canapply' => true,
            'canrestore' => true,
        ]), true);

        foreach ($data['patches'][0]['actions'] as $action) {
            $this->assertStringContainsString('/local/patchmanager/index.php', $action['url']);
            $this->assertStringContainsString('sesskey=', $action['url']);
            $this->assertStringContainsString('pack=local_zoomcustom', $action['url']);
            $this->assertStringContainsString('patch=001-period-grading', $action['url']);

            // The block never carries a confirmation flag: confirming is the
            // Patch Manager page's job, behind its own POST form.
            $this->assertStringNotContainsString('confirm=1', $action['url']);
        }

        // The Check link uses the existing sesskey protected action.
        $this->assertStringContainsString('action=check', $data['checkurl']);
        $this->assertStringContainsString('sesskey=', $data['checkurl']);

        // Manage is a plain link to the page.
        $this->assertStringContainsString('/local/patchmanager/index.php', $data['manageurl']);
        $this->assertStringNotContainsString('action=', $data['manageurl']);
    }

    /**
     * With the web-apply switch off, Verify is still offered but Apply is not.
     *
     * This is the split the gating fix introduces: verifying records a decision
     * and writes no code, so it must not depend on a switch whose purpose is
     * permitting code writes from a browser.
     *
     * @return void
     */
    public function test_verify_offered_without_webapply_but_apply_is_not(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Exactly what api::can_manage_action() returns when allowwebapply is off.
        $webapplyoff = [
            'apply' => false,
            'reapply' => false,
            'restore' => false,
            'verify' => true,
            'acknowledge' => true,
        ];

        $data = $this->export($this->snapshot([
            'canapply' => true,
            'canreapply' => true,
            'canrestore' => true,
            'canverify' => true,
        ]), $webapplyoff);

        $actions = array_column($data['patches'][0]['actions'], 'action');
        $this->assertSame(['verify'], $actions,
                'only verify may be offered while browser code-writing is off');
        $this->assertTrue($data['canmanage']);
    }

    /**
     * An action the caller did not resolve is never offered.
     *
     * @return void
     */
    public function test_unresolved_action_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = $this->export($this->snapshot(['canverify' => true]), []);

        $this->assertSame([], $data['patches'][0]['actions']);
        $this->assertFalse($data['canmanage']);
    }

    /**
     * An unreadable engine degrades to a notice rather than an error.
     *
     * @return void
     */
    public function test_unavailable_engine_is_handled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = $this->export(['generated' => time(), 'available' => false, 'patches' => []], true);

        $this->assertFalse($data['available']);
        $this->assertFalse($data['haspatches']);
        $this->assertNull($data['overall']);
    }

    /**
     * The template renders from the exported data without error.
     *
     * @return void
     */
    public function test_template_renders(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/my/index.php');

        $renderer = $PAGE->get_renderer('block_patchmanager');
        $html = $renderer->render(
            new summary($this->snapshot(['canverify' => true]), $this->permissions(true)));

        $this->assertStringContainsString('Period grading', $html);
        $this->assertStringContainsString('mod_zoom', $html);
        $this->assertStringContainsString(get_string('manage', 'block_patchmanager'), $html);
    }
}
