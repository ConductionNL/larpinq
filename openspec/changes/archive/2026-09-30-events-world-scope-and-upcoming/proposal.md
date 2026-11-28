---
kind: code
depends_on: []
---

# Proposal: events-world-scope-and-upcoming

## Summary

A group that runs two campaigns wants its fantasy weekends and its sci-fi
evenings apart, and everyone wants to see what is coming up next. Larpinq has
an event world field that no page can set, an active-world switcher that its
own spec requires and that was never built, and an events list and dashboard
widget that mix past and future. This change lets a game master set an
event's world on the event form and see it on the event page, builds the
per-user active-world switcher (stored with the existing preferences API) that
narrows lists to one world, and shows upcoming events by default on the Events
page and the dashboard.

## Motivation

Three rows of the larpinq capability matrix
(`openspec/parity/capabilities.json`, compared 2026-09-26), each rated
partial and built with two or more competitors rated yes; the OpenSpec pass of
2026-09-27 decided `build` for the missing half of each.

**`evt-world-scoping`**, "Restrict an event to one campaign world so it
doesn't mix with other worlds' characters and events." Matrix evidence:
"lib/Settings/larpinq_register.json:833-840 (event.setting: uuid $ref setting,
description 'Optional world (campaign) this event belongs to', visible: false);
no widget or related-group on EventDetail (src/manifest.json:1589-1820)
references a 'setting'/'World' group". The note: "nothing lets a user actually
set an event's own world from the UI." Four competitors rate it yes:

- LarpManager: "larpmanager/models/event.py:196-206 each event belongs to at most one campaign parent, larpmanager/models/writing.py:51-91 elements resolve only within that campaign" (source read at main 36f23d3).
- MyLARP: "Each event lives on its own campaign subdomain, so it cannot mix with another campaign's characters/events. https://mylarp.com"
- LARP Portal: "Characters, points and events are held within one campaign's account, keeping games from mixing. https://larportal.com"
- Kanka: "events belong to one campaign (app/Models/Event.php:31-36 campaign_id)" (source read at tag 3.15).

**`adm-user-preferences`**, "Keep small per-user UI preferences (guided tour
seen, setup completed, a dismissed dialog) via a generic preferences store."
Matrix evidence: "appinfo/routes.php:46-47 (preferences#getPreference GET
/api/preferences/{key}, preferences#setPreference PUT ...);
lib/Controller/PreferencesController.php:1-9 generic per-user IConfig-backed
key/value store". The note: "The seed's example ('a default world per user')
is not implemented". Three competitors rate it yes:

- LarpManager: "larpmanager/models/member.py:480-508 MemberConfig key-value store, larpmanager/urls/user.py:426-429 set_member_config, larpmanager/urls/lm.py:175 demo_hint_dismiss, dismiss_sticky_message".
- LARP Portal: "Player Module lets players 'manage their passwords and opt in to receive notifications, and manage their own profile' ... https://larportal.com/how-it-works.php"
- Kanka: "per-user settings and dismissed tutorials (app/Models/UserSetting.php; routes/settings.php:83-84,141-142)".

**`evt-upcoming-list`**, "See the upcoming events at a glance." Matrix
evidence: "Events index page (generic list/sort/search for larping_event, no
default 'future only' filter); src/manifest.json Dashboard 'recent-events'
widget (type object-table, sort startDate desc, limit 6, no filter on startDate
>= now)". Four competitors rate it yes:

- LarpManager: "larpmanager/views/user/event.py:92 calendar of open runs, larpmanager/cache/links.py:207 open_runs versus past_runs".
- LARP Portal: "'Event scheduling and notifications' implies players see upcoming events. https://larportal.com"
- Kanka: "dashboard calendar widget of upcoming reminders (app/Enums/Widget.php Calendar; routes/campaigns/campaign.php:186-188); calendars.events list routes/campaigns/entities.php:185".
- pretix: "src/pretix/control/views/dashboards.py:664 user_index shows widgets for the user's events (:513 widgets_for_event_qs), and src/pretix/control/forms/filter.py:1966 filters the event list to running or future events".

The archived `setting-management` change added `event.setting` and specified
the active-world lens (requirement "A per-user active setting MUST filter lists
server-side" in `openspec/specs/setting-management/spec.md`), then deferred the
switcher (tasks 3.1 to 3.4). This change builds it. All three rows are about
which events a user sees on the same two screens.

## Affected Projects

- [ ] Project: `larpinq`: the event world on the event pages, the active-world switcher and lens, and upcoming defaults on the Events page and the dashboard.

## Scope

### In Scope

- `event.setting` visible and editable on the event form and shown on EventDetail and in the Events index.
- The active-world switcher (a world or "All worlds"), stored per user under the preferences key `active-world`, as the `setting-management` spec requires.
- Index pages and dashboard widgets of world-scoped schemas narrowed to the active world plus shared objects, in the list query.
- The Events index opening on upcoming events (start date today or later, soonest first) with a toggle to past events; the dashboard widget "Upcoming events" listing the next 6.

### Out of Scope

- Pickers defaulting to the active world (the spec's next requirement): the relation filters on `setting` already narrow them to the object's own world.
- A calendar view (the calendar leaf covers it).

## Approach

Manifest edits for the event field, the Events index default filter and the
dashboard widget. A `WorldSwitcher.vue` header component and a
`useActiveWorld()` composable read and write the preference and add the world
filter to list fetches of world-scoped schemas. Details in design.md.

## New Dependencies

None.

## Impact

- `lib/Settings/register.d/events-world-scope-and-upcoming.json` (new): `event.setting` visible.
- `src/manifest.json`: EventDetail and the Events index show the world; the Events index default filter and sort; the dashboard widget (in place).
- `src/components/WorldSwitcher.vue`, `src/composables/useActiveWorld.js` (new), registered as a `header` kind in `src/registry.js`.

## Cross-Project Dependencies

OpenRegister list filters: "this world or no world" needs an `or` or `in`
with empty in one list query. If the installed OpenRegister cannot express it,
the lens shows the world's own objects plus a second query for shared ones on
the dashboard only, and the gap is reported for openregister.

## Risks

### Risk 1: A user forgets a world is active and thinks data is missing
**Severity:** Medium. **Mitigation:** the switcher shows the active world in every list header, and an empty list under a world says "No events in <world>. Show all worlds."

### Risk 2: An archived active world
**Severity:** Low. **Mitigation:** the spec's fall-back to "All worlds" when the stored world is archived or gone.

## Rollback Strategy

Remove the header component and revert the manifest edits; the preference
stays unused.

## Open Questions

None.
