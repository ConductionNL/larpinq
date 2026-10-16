# Design: leaf-integrations

## Architecture Overview
No new architecture. This is the third `register.d` wave: the five shipped
leaves and the MCP adoption all travel the same path —
`ConfigFileLoaderService::loadConfigurationFile()` deep-merges (ADR-037)
every `lib/Settings/register.d/*.json` fragment into
`larpinq_register.json`, OpenRegister imports the merged register, and the
integration registry (`IntegrationRegistry::getEnabled()`, ADR-019) renders a
leaf tab/widget on the object detail page for each `linkedTypes` value whose
provider's required NC app is enabled.

```
event detail page (ObjectDetail host, nc-vue leaf host)
  -> IntegrationRegistry::getEnabled(event object)
    -> linkedTypes on the merged `event` schema:
       calendar  (event-calendar-leaf.json)          [shipped]
       maps      (event-location-to-maps-leaf.json)  [shipped]
       forms     (event-signup-to-forms-leaf.json)   [shipped]
       talk, polls, deck  (leaf-integrations.json)   [THIS CHANGE]
```

## Current state (verified at HEAD)
- `register.d/` fragments: `event-calendar-leaf.json` (`["calendar"]`),
  `event-location-to-maps-leaf.json` (`["maps"]`),
  `event-signup-to-forms-leaf.json` (`["forms"]`),
  `player-to-contacts-leaf.json` (`["contacts"]` on `player`),
  `event-checkin-roster.json` (adds the `attendance` schema),
  `larpinq-mcp-adoption.json`, `portal-identity.json`.
- Monolith: `character.configuration.linkedTypes` carries `photos`.
- OR providers at HEAD: `TalkProvider` (`getId() = 'talk'`), `PollsProvider`
  (`'polls'`), `DeckProvider` (`'deck'`).
- Nothing anywhere declares talk, polls, or deck — these are the actual gaps
  (maps and forms, sometimes assumed missing, are shipped).

## Per-leaf rationale

| Leaf | Schema(s) | LARP workflow | Why a leaf, not a widget |
|------|-----------|---------------|--------------------------|
| talk | `event`, `setting` | Session-day coordination room per event; standing campaign room per setting ("where do we discuss Saturday?") | Talk owns rooms, presence, and history; the leaf links a room to the object (ADR-022 anti-pattern: app-local chat) |
| polls | `event`, `setting` | Scheduling votes ("which Saturday suits everyone?") on the event; rules-revision votes on the campaign | Polls owns ballots and anonymity settings; outcome adoption stays a human edit (REQ-005) |
| deck | `event` | GM prep board: props, NPC briefs, plot beats, cooking rota | Deck owns boards/cards/assignees; the leaf links the board to the event record |

Not declared: `deck` on `setting` (no concrete workflow yet; bias to fewer —
same discipline that deferred `event.create` in the MCP adoption), and any
leaf on `character`/`player`/`skill`/`item`/`condition`/`xpAward`/
`attendance` (no per-object chat/vote/board makes sense; `player` and
`character` already carry their person/photos leaves).

## The merge question (Risk 1)
`event` receives `linkedTypes` contributions from three existing fragments
plus this one. The fragment shape below assumes ADR-037's recursive deep
merge unions array values under the same key path; before finalising, task
T2 measures `mergeRegisterFragments()` with the real fragment set and records
whether arrays union or last-writer-wins. If last-writer-wins, this fragment
must instead declare the full six-value array — the acceptance criterion is
deliberately written against the *merged* result (all six values present), so
either mechanism is caught.

```json
{
  "components": {
    "schemas": {
      "event": {
        "configuration": {
          "linkedTypes": ["talk", "polls", "deck"]
        }
      },
      "setting": {
        "configuration": {
          "linkedTypes": ["talk", "polls"]
        }
      }
    }
  }
}
```

## Degradation and i18n
Leaves hide when Talk/Polls/Deck are absent (provider `requiredApp`); the
detail pages render without error — the contract the shipped leaves already
honour, restated as REQ-004 so the new leaves cannot regress it. Any new tab
labels come from the leaf apps through the registry host; Larpinq adds no
hard-coded label strings.

## Measured merge semantics (task 1, 2026-09-29)

`ConfigFileLoaderService::deepMergeConfig()` concatenates two list arrays
(`array_merge`), and fragments merge in sorted file order. So a fragment that
declares only its own values adds them next to the ones other fragments
contribute: the fragment carries the three-value delta for `event` and the
two-value list for `setting` (which has no `configuration` in the monolith).
`tests/unit/Settings/LeafIntegrationsFragmentTest.php` runs the real merge over
the real monolith and every fragment, and asserts all six event leaves, each
once, and talk and polls without deck on the setting.

CHANGELOG (task 5) is not edited: `lib/Settings/register.d/README.md` gives the
changelog to the release step, not to build branches. The release notes pick
the change up from its PR title.
