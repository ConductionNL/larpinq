# Design: larpinq-mcp-adoption

## Architecture Overview
No new architecture. Larpinq already loads its OpenRegister configuration
from `lib/Settings/larpinq_register.json`, recursively deep-merged (ADR-037)
with every `*.json` fragment in `lib/Settings/register.d/` by
`ConfigFileLoaderService::loadConfigurationFile()` /
`mergeRegisterFragments()`. This change adds exactly one new fragment,
`register.d/larpinq-mcp-adoption.json`, that contributes a
`configuration.x-openregister-mcp` block to 9 existing schema definitions —
per `register.d/README.md`'s explicit rule, this change does **not** edit
`larpinq_register.json` directly. OpenRegister's `SchemaDerivedToolProvider`
(cross-repo, already merged at openregister@origin/development) then derives
`larpinq.<schema>.<verb>` tools at MCP-serve time.

```
Hermiq (agent)
  -> OpenRegister /api/mcp (JSON-RPC) or chat facade
    -> SchemaDerivedToolProvider
      -> merged larpingapp register (monolith + register.d/*.json, incl.
         event-calendar-leaf, event-checkin-roster, event-location-to-maps-leaf,
         event-signup-to-forms-leaf, player-to-contacts-leaf, portal-identity)
        -> configuration.x-openregister-mcp per schema  (THIS CHANGE adds these)
```

The recursive merge means this fragment's additions land alongside content
already contributed by other fragments to the same schema keys — e.g.
`character` already carries `configuration.x-openregister-lifecycle` (the
`approved` state machine) from the base register and an `ownerRef` property
from `portal-identity.json`; this change adds a sibling
`configuration.x-openregister-mcp` key without touching either.

## Curation — schema -> verbs -> why

11 schemas exist in the larpingapp register at HEAD (10 in the monolith
`larpinq_register.json` + `attendance`, added by the
`event-checkin-roster.json` fragment — verified via property dumps of both).
9 are curated ON, 2 are OFF. 6 of the 9 are read-only; 3 additionally carry one
write verb each, individually argued.

| Schema | Verbs | Why (one line) |
|---|---|---|
| `character` | search, get, **create** | Core entity — "show me character X's sheet", "who's approved to play in setting Y"; `create` is safe because it only ever produces a draft in the `approved: "no"` lifecycle state (schema's own `configuration.x-openregister-lifecycle`) — nothing is live until a GM runs the existing `approved` transition. |
| `player` | search, get | "Who controls character X" / player lookup — identity itself is synced to Nextcloud Contacts via the `player-to-contacts-leaf` fragment, so writes stay in that leaf's flow, not chat. |
| `setting` | search, get | "Which campaigns/worlds are currently running" — creating a new campaign is a structural GM decision made in the admin UI, not a conversational action. |
| `skill` | search, get | "What does skill X do / what's available to buy with XP" — game-design reference content authored by GMs outside chat. |
| `item` | search, get | "What items are unique / what does item X grant" — same reference-content reasoning as `skill`. |
| `condition` | search, get | "What does condition X do to a character" — same reference-content reasoning as `skill`/`item`. |
| `event` | search, get | "What events are coming up / who's attending event X" — explicitly requested; kept read-only because event creation fans out to 3 leaf integrations (see Decision 2 below). |
| `xpAward` | search, get, **create** | "How much XP has character X earned" / "award Alice 5 XP for tonight" — the schema's own description literally documents this as "the post-event awarding ritual"; purely additive audit data, OR `authorization` already restricts create/update/delete to `gamemasters`. |
| `attendance` | search, get, **update** | "Check Bob in at the door" — the record already exists from event signup (`event-signup-to-forms-leaf`); the GM's day-of action is moving `status` forward, not creating a new record. `checkedInAt`/`checkedInBy` are server-stamped and never accepted from the client per the schema's own description, and OR `authorization` restricts update to `gamemasters`. |

### Excluded (2 schemas) — what and why

| Schema | Why excluded |
|---|---|
| `ability` | Internal numeric stat/XP-engine primitive (Strength, XP, etc.) that `effect` records reference by id; a user's real question ("what does the Berserker condition do") is answered via `skill`/`item`/`condition`/`event`, not by looking up a raw ability record. Omitted to avoid a low-value, rarely-addressed lookup surface. |
| `effect` | Internal stat-modifier primitive consumed by `skill`/`item`/`condition`/`event` via their `effects` arrays; the parent schemas carry the user-meaningful name/description, so a standalone `effect.search` would duplicate that lookup path for marginal benefit. Bias to fewer. |

**Why so few writes overall, and why these three specifically:** the task
brief explicitly frames Larpinq as lower-stakes than a government catalogue
("a game"), so writes are more defensible here — but each of the three chosen
is still individually bounded by an existing mechanism this change merely
exposes, not a new one it invents:
- `character.create` → bounded by the pre-existing `approved` lifecycle gate.
- `xpAward.create` → bounded by pre-existing OR `authorization: { create:
  ["gamemasters"] }` and is purely additive (no destructive or idempotency
  concern — every award is its own audit row by design).
- `attendance.update` → bounded by pre-existing OR `authorization: { update:
  ["gamemasters"] }`, by the fact that `checkedInAt`/`checkedInBy` are
  server-stamped regardless of client input, and by the record already
  existing (this is a status transition, not free-form field mutation).

`event.create` was explicitly considered and rejected (Decision 2): unlike the
three enabled writes, it has no existing bounding mechanism — it would trigger
calendar/maps/forms side effects in three other apps with no human confirming
each artifact. `skill.create`/`item.create`/`condition.create` were also
considered and rejected: these are game-design/balance content with no
lifecycle gate at all (unlike `character`), so a bad agent-authored skill
immediately becomes purchasable — no bounding mechanism exists to defer to.

## `configuration.x-openregister-mcp` — exact per-schema declaration

All 9 blocks below go into
`lib/Settings/register.d/larpinq-mcp-adoption.json`, one entry per schema
under `components.schemas.<name>.configuration.x-openregister-mcp`. Every
`filters` entry was cross-checked against the schema's `properties` map at
HEAD (base register for `character`/`player`/`setting`/`skill`/`item`/
`condition`/`event`/`xpAward`; `event-checkin-roster.json` fragment for
`attendance`); every one is a real property.

```json
{
  "components": {
    "schemas": {
      "character": {
        "configuration": {
          "x-openregister-mcp": {
            "enabled": true,
            "tools": {
              "search": {
                "description": "Search characters by name, type (player/npc/other), approval status, campaign setting, or owning player — use this to find a specific character sheet or list who's approved for play in a setting.",
                "scope": "read",
                "filters": ["name", "type", "approved", "setting", "ownerUid"],
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              },
              "get": {
                "description": "Get a single character's full sheet: stats, skills, items, conditions, and event history.",
                "scope": "read",
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              },
              "create": {
                "description": "Create a new draft character (defaults to type=player, approved=no) — the assistant-assisted 'help me build a character' flow. The character is not usable in play until a game master runs the existing approval transition.",
                "scope": "create",
                "readOnlyHint": false,
                "destructiveHint": false,
                "idempotentHint": false
              }
            }
          }
        }
      },
      "player": {
        "configuration": {
          "x-openregister-mcp": {
            "enabled": true,
            "tools": {
              "search": {
                "description": "Search players by name or linked Nextcloud user id — use this to find who controls a given character.",
                "scope": "read",
                "filters": ["name", "userUid"],
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              },
              "get": {
                "description": "Get a single player's profile and description.",
                "scope": "read",
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              }
            }
          }
        }
      },
      "setting": {
        "configuration": {
          "x-openregister-mcp": {
            "enabled": true,
            "tools": {
              "search": {
                "description": "Search LARP settings (worlds/campaigns) by name or status (active/archived) — use this to see which campaigns are currently running versus finished.",
                "scope": "read",
                "filters": ["name", "status"],
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              },
              "get": {
                "description": "Get a single setting's detail — the campaign that scopes its characters, events, and rules.",
                "scope": "read",
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              }
            }
          }
        }
      },
      "skill": {
        "configuration": {
          "x-openregister-mcp": {
            "enabled": true,
            "tools": {
              "search": {
                "description": "Search purchasable skills by name or campaign setting — use this to look up what a skill does or what's available to buy with experience points.",
                "scope": "read",
                "filters": ["name", "setting"],
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              },
              "get": {
                "description": "Get a single skill's prerequisites (required skills, stats, conditions, effects) and the effects it grants.",
                "scope": "read",
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              }
            }
          }
        }
      },
      "item": {
        "configuration": {
          "x-openregister-mcp": {
            "enabled": true,
            "tools": {
              "search": {
                "description": "Search magical/special items by name, uniqueness, or campaign setting.",
                "scope": "read",
                "filters": ["name", "unique", "setting"],
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              },
              "get": {
                "description": "Get a single item's granted effects and which characters currently hold it.",
                "scope": "read",
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              }
            }
          }
        }
      },
      "condition": {
        "configuration": {
          "x-openregister-mcp": {
            "enabled": true,
            "tools": {
              "search": {
                "description": "Search gameplay conditions (consequences applied to characters) by name, uniqueness, or campaign setting.",
                "scope": "read",
                "filters": ["name", "unique", "setting"],
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              },
              "get": {
                "description": "Get a single condition's granted effects and which characters currently carry it.",
                "scope": "read",
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              }
            }
          }
        }
      },
      "event": {
        "configuration": {
          "x-openregister-mcp": {
            "enabled": true,
            "tools": {
              "search": {
                "description": "Search LARP events by name, campaign setting, or location — use this to find upcoming or past gatherings.",
                "scope": "read",
                "filters": ["name", "setting", "location"],
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              },
              "get": {
                "description": "Get a single event's schedule, location, and subscribed players.",
                "scope": "read",
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              }
            }
          }
        }
      },
      "xpAward": {
        "configuration": {
          "x-openregister-mcp": {
            "enabled": true,
            "tools": {
              "search": {
                "description": "Search experience-point awards by event or character — use this to see a character's XP history or everyone awarded XP at a given event.",
                "scope": "read",
                "filters": ["event", "character"],
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              },
              "get": {
                "description": "Get a single XP award's amount, reason, and awarding game master.",
                "scope": "read",
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              },
              "create": {
                "description": "Record a single experience-point award for one character at one event — the post-event 'award XP to each attendee' ritual this schema exists for. Purely additive audit data; OpenRegister authorization restricts this write to the gamemasters group regardless of who calls it via MCP.",
                "scope": "create",
                "readOnlyHint": false,
                "destructiveHint": false,
                "idempotentHint": false
              }
            }
          }
        }
      },
      "attendance": {
        "configuration": {
          "x-openregister-mcp": {
            "enabled": true,
            "tools": {
              "search": {
                "description": "Search event attendance/check-in records by event, character, or status (registered/checked-in/no-show).",
                "scope": "read",
                "filters": ["event", "character", "status"],
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              },
              "get": {
                "description": "Get a single attendance record's status and check-in timestamp.",
                "scope": "read",
                "readOnlyHint": true,
                "destructiveHint": false,
                "idempotentHint": true
              },
              "update": {
                "description": "Move a participant's day-of attendance status forward at check-in (registered to checked-in, or to no-show). The record itself is created when the character signs up for the event; this only advances its status. checkedInAt/checkedInBy are always server-stamped and cannot be set by this call. OpenRegister authorization restricts this write to the gamemasters group.",
                "scope": "update",
                "readOnlyHint": false,
                "destructiveHint": false,
                "idempotentHint": true
              }
            }
          }
        }
      }
    }
  }
}
```

## Goals / Non-Goals
**Goals:** derive an MCP surface over the 9 schemas a player/GM assistant
would plausibly be asked about, with 3 individually-argued, pre-bounded write
verbs, using only the existing ADR-037/ADR-063 mechanisms.

**Non-Goals:** no `delete` verb anywhere; no write on `event`, `setting`,
`skill`, `item`, `condition`, or `player`; no new PHP; no change to the
fragment-merge mechanism or any existing `register.d/*.json` fragment.

## Decisions

### Decision 1: `register.d/` fragment, not editing the monolith
`register.d/README.md` is explicit: "Do not edit `larpinq_register.json`,
`appinfo/info.xml`, or `CHANGELOG.md` in a build branch — the single version
bump is owned by the apply/release step." This change follows that rule
exactly, unlike the pipelinq `client`/`lead` exemplar which edited the
monolith directly (pipelinq's own README carries no equivalent prohibition).
**Alternative considered:** matching pipelinq's exemplar byte-for-byte and
editing `larpinq_register.json` — rejected because it would violate this
app's own documented fragment-ownership convention.

### Decision 2: `event` stays read-only despite being a strong write candidate
"Schedule a new event" is exactly the kind of request a LARP assistant should
handle. Rejected for this change because `event` carries `linkedTypes`
contributed by three separate `register.d/*.json` fragments
(`event-calendar-leaf`, `event-location-to-maps-leaf`,
`event-signup-to-forms-leaf`) — creating an event is documented elsewhere in
this app as triggering calendar, maps/geocoding, and signup-form provisioning
in three other apps. An agent-initiated `create` would fan out those side
effects without a human confirming each downstream artifact, unlike
`character.create` (bounded by a lifecycle gate) or `xpAward.create`/
`attendance.update` (bounded by RBAC + append-only/status-only semantics).
Deferred to a future change once the leaf integrations' idempotency/rollback
story is designed (see `DEFERRED_QUESTIONS`).

### Decision 3: `attendance` gets `update`, not `create`
Considered enabling `create` (in case no signup record exists yet, e.g. a
walk-in). Rejected for this change: the schema's own description says "One
record per (event, character) pair" and frames the day-of GM action as a
`status` move ("registered → checked-in or no-show"), implying the record is
expected to already exist from signup. Scoping to `update` only is the more
conservative, better-argued choice; a `create` path for walk-ins can be added
later if that workflow turns out to be real (see `DEFERRED_QUESTIONS`).

## Risks / Trade-offs
[Risk] A future schema property rename silently breaks a declared `filters`
entry → Mitigation: OpenRegister's `McpAnnotationValidator` hard-rejects the
whole schema import when a filter names a non-existent property, so this fails
loudly at import time.

[Risk] `character.create` is called by an agent many times, flooding the
roster with unused drafts → Mitigation: drafts are inert (`approved: "no"`,
excluded from play by the app's own lifecycle gate) and remain visible/
cleanable by a GM through the existing character list; no new cleanup
mechanism is introduced by this change, and none is needed for a config-only
dialect declaration.

[Trade-off] `event.create` is deliberately deferred, so an agent cannot yet
schedule an event end-to-end via chat → Accepted: the side-effect fan-out
across 3 leaf integrations needs its own design, not a MCP dialect tack-on.

## Migration Plan
1. Add `register.d/larpinq-mcp-adoption.json` with the 9 blocks above.
2. `python3 -m json.tool` validate the new fragment.
3. Re-run `ConfigFileLoaderService`'s configuration load (via the existing
   repair/import path) so the fragment signature changes and OpenRegister
   re-imports the merged register.
4. Verify via OpenRegister's MCP tool listing that
   `larpinq.character.search`, `.get`, `.create`, etc. (21 tools total: 9
   schemas × search/get + 3 extra write verbs) appear.
5. **Rollback:** delete the fragment file (or flip every `enabled` to
   `false`) and re-run the import — see proposal.md Rollback Strategy.

## Seed Data
Not applicable — this change annotates 9 *existing* schemas with MCP metadata;
it introduces no new schema and no new objects. The app's existing seed data
(characters, players, events, etc.) is unaffected and sufficient to exercise
the new derived tools, including the three write verbs, once imported.

## Trade-offs
See "Risks / Trade-offs" above.

## DEFERRED_QUESTIONS
- Should `event.create` be enabled in a follow-up change once the
  calendar/maps/forms leaf integrations have a documented idempotency/rollback
  story for agent-triggered creation? Deferred — no issue filed yet.
- Should `attendance.create` be added for walk-in participants with no prior
  signup record? Deferred until a real walk-in workflow is confirmed; revisit
  if GMs report this as a gap.
- Should `skill`/`item`/`condition` ever get a curated, non-CRUD
  `#[McpTool]` action (e.g. "propose a new skill for GM review", landing in a
  draft/pending state analogous to `character.approved`) rather than raw
  `create`? Deferred — no lifecycle gate exists yet for these schemas to bound
  such a write; would need its own design.
