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
 * Patch Manager Dashboard block.
 *
 * @package    block_patchmanager
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Shows the state of the site's code customisations on the Dashboard.
 *
 * The block is a read-only view. It never applies, restores or verifies
 * anything: every action is a link to the Patch Manager page, which keeps its
 * own confirmation screen, POST requirement, sesskey check, site-admin check
 * and web-apply flag.
 *
 * @package    block_patchmanager
 * @copyright  2026 Pedago Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_patchmanager extends block_base {

    /**
     * Initialise the block.
     *
     * @return void
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_patchmanager');
    }

    /**
     * Where this block may be added.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return ['my' => true];
    }

    /**
     * Only one instance is useful; it always shows every registered patch.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * No global configuration.
     *
     * @return bool
     */
    public function has_config(): bool {
        return false;
    }

    /**
     * Do not cache the rendered block: what it shows depends on the viewer's
     * permissions, and the underlying data has its own short lived cache.
     *
     * @return array
     */
    public function html_attributes(): array {
        $attributes = parent::html_attributes();
        $attributes['class'] .= ' block_patchmanager';
        return $attributes;
    }

    /**
     * Build the block body.
     *
     * Returns empty content for anyone without local/patchmanager:view, which
     * means Moodle renders nothing at all rather than an empty shell.
     *
     * @return stdClass
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // The engine owns the read permission. Someone who cannot see the Patch
        // Manager page must not see its contents here either.
        $context = context_system::instance();
        if (!has_capability('local/patchmanager:view', $context)) {
            return $this->content;
        }

        // The dependency guarantees the engine is installed, but a half finished
        // upgrade should degrade quietly rather than break the Dashboard.
        if (!class_exists('\local_patchmanager\api')) {
            return $this->content;
        }

        $snapshot = \block_patchmanager\local\statuscache::get();

        // Whether to offer an action is a per-user question, so it is asked on
        // every render and never cached. It is also per-action: applying writes
        // code and needs the web-apply switch, while verifying records a
        // decision and does not. can_manage_action() is the engine's own rule,
        // asked once per action so the block never restates it;
        // require_manage_action() still gates the action itself.
        $permissions = [];
        foreach (\local_patchmanager\api::MANAGED_ACTIONS as $action) {
            $permissions[$action] = \local_patchmanager\api::can_manage_action($action, true);
        }

        // Authorisation without the web-apply switch: lets the block tell "not
        // an admin, hide this" apart from "an admin, but this site keeps code
        // changes CLI-only", which is the site's own choice, not a permission
        // gap. Still asked fresh on every render, like $permissions above.
        $baseallowed = \local_patchmanager\api::can_manage(false);

        $renderable = new \block_patchmanager\output\summary($snapshot, $permissions, $baseallowed);
        $renderer = $this->page->get_renderer('block_patchmanager');
        $this->content->text = $renderer->render($renderable);

        return $this->content;
    }
}
