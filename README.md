# block_patchmanager

A Dashboard block showing the state of this site's code customisations.

Install at `blocks/patchmanager/`. Requires
[`local_patchmanager`](https://github.com/mezbaur2004/moodle-local-patchmanager)
`2026092002` or later.

## What it is

A read-only view over `local_patchmanager`. The block holds no patch logic of
its own: it asks the engine for status and renders the answer. Every action is a
link to the Patch Manager page, which keeps its own confirmation screen, POST
requirement, sesskey check, site-admin check and web-apply flag.

**The block never applies, reapplies, restores or verifies anything.** Rendering
the Dashboard is a GET, so a render performs no write of any kind — not to the
database, not to config, not to the filesystem.

## What it shows

Per registered customisation:

| Field | Source |
|---|---|
| Overall status | `status::state_label()` and `status::severity()` |
| Customisation name | the patch definition |
| Target component and version | `status->componentversion`, including a pending upgrade |
| Verification status | `status->verified`, `verifiedsource`, `verificationstale` |
| Last check time | when the cached snapshot was computed |
| Heartbeat | the owning pack's `<pack>\heartbeat::info()`, when it publishes one |

The heartbeat lookup is pack-agnostic: the block derives the class name from the
registered pack rather than naming any particular one, and shows nothing when a
pack does not provide it. The engine has no heartbeat of its own, because
whether customised code can be observed executing is a question only a pack can
answer.

## Caching

`api::build_status()` reads every target file from disk, probes writability and
OPcache, and queries the audit tables, for each patch. That is right for the
Patch Manager page and the scheduled check, but far too much for every Dashboard
view.

The block therefore reads a short-lived snapshot (`db/caches.php`, 300s TTL)
holding only site-level facts, so one cached copy is correct for every viewer.
The per-user decision about which actions to offer is made fresh on every render
and is never cached.

A live check remains available through the explicit **Check now** action, which
uses the engine's existing sesskey-protected mechanism.

## Permissions

- `block/patchmanager:addinstance`, `block/patchmanager:myaddinstance` control
  who may place the block.
- Content is gated on `local/patchmanager:view`. Without it the block renders
  nothing at all, so adding the block can never reveal patch state to someone who
  could not already read it on the Patch Manager page.
- Action links appear only when the engine's own `status::can_*()` rule allows
  the action **and** `api::can_manage(true)` passes for this user.

## Licence

GNU GPL v3 or later.
