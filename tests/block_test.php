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

/**
 * Capability gating and read-only guarantees for the Patch Manager block.
 *
 * @package    block_patchmanager
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_patchmanager
 */
final class block_test extends \advanced_testcase {

    /**
     * Build the block against a real page.
     *
     * @return \block_patchmanager
     */
    private function make_block(): \block_patchmanager {
        global $CFG, $PAGE;

        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/patchmanager/block_patchmanager.php');

        $PAGE->set_url('/my/index.php');
        $PAGE->set_context(\context_system::instance());

        $block = new \block_patchmanager();
        $block->page = $PAGE;
        $block->instance = (object) ['id' => 0, 'configdata' => ''];

        return $block;
    }

    /**
     * The block is offered on the Dashboard.
     *
     * @return void
     */
    public function test_applicable_formats(): void {
        $this->resetAfterTest();
        $block = $this->make_block();

        $formats = $block->applicable_formats();
        $this->assertArrayHasKey('my', $formats);
        $this->assertTrue($formats['my']);
    }

    /**
     * Both capabilities exist with the expected contexts.
     *
     * @return void
     */
    public function test_capabilities_are_declared(): void {
        global $CFG;
        $this->resetAfterTest();

        $capabilities = [];
        require($CFG->dirroot . '/blocks/patchmanager/db/access.php');

        $this->assertArrayHasKey('block/patchmanager:addinstance', $capabilities);
        $this->assertArrayHasKey('block/patchmanager:myaddinstance', $capabilities);

        $this->assertSame(CONTEXT_BLOCK, $capabilities['block/patchmanager:addinstance']['contextlevel']);
        $this->assertSame(CONTEXT_SYSTEM, $capabilities['block/patchmanager:myaddinstance']['contextlevel']);

        foreach (['addinstance', 'myaddinstance'] as $name) {
            $this->assertSame(CAP_ALLOW,
                    $capabilities['block/patchmanager:' . $name]['archetypes']['manager'] ?? null,
                    $name . ' must be allowed for manager');
        }
    }

    /**
     * A user without local/patchmanager:view gets no content at all.
     *
     * @return void
     */
    public function test_no_content_without_view_capability(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertFalse(has_capability('local/patchmanager:view', \context_system::instance()));

        $block = $this->make_block();
        $content = $block->get_content();

        $this->assertSame('', $content->text);
        $this->assertTrue($block->is_empty());
    }

    /**
     * A manager with the view capability does get content.
     *
     * @return void
     */
    public function test_content_for_user_with_view_capability(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/patchmanager:view', CAP_ALLOW, $roleid, $context->id, true);
        role_assign($roleid, $user->id, $context->id);
        $this->setUser($user);

        $this->assertTrue(has_capability('local/patchmanager:view', $context));

        $block = $this->make_block();
        $content = $block->get_content();

        // Content is produced. What it says depends on what is registered.
        $this->assertNotNull($content);
        $this->assertIsString($content->text);
    }

    /**
     * Rendering the block must never change any patch state.
     *
     * Reading the Dashboard is a GET. If rendering could write, a crawler or a
     * prefetch could change the site's code, so this asserts that the audit
     * trail is untouched by a render.
     *
     * @return void
     */
    public function test_render_performs_no_writes(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $before = $DB->count_records('local_patchmanager_audit');
        $backupbefore = $DB->count_records('local_patchmanager_backup');
        $verifybefore = $DB->count_records('local_patchmanager_verify');

        $block = $this->make_block();
        $block->get_content();

        $this->assertSame($before, $DB->count_records('local_patchmanager_audit'),
                'rendering the block must not write an audit record');
        $this->assertSame($backupbefore, $DB->count_records('local_patchmanager_backup'),
                'rendering the block must not create a backup');
        $this->assertSame($verifybefore, $DB->count_records('local_patchmanager_verify'),
                'rendering the block must not record a verification');
    }

    /**
     * The block class exposes no method that could change state.
     *
     * @return void
     */
    public function test_block_exposes_no_write_entry_points(): void {
        $this->resetAfterTest();

        $forbidden = ['apply', 'reapply', 'restore', 'verify', 'acknowledge'];
        $methods = get_class_methods(\block_patchmanager::class);

        foreach ($forbidden as $name) {
            $this->assertNotContains($name, $methods,
                    'the block must not expose a ' . $name . '() entry point');
        }
    }
}
