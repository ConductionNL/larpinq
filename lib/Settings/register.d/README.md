# Register fragments (ADR-037)

Each `*.json` file in this directory is deep-merged over the monolithic
`lib/Settings/larpinq_register.json` by `ConfigFileLoaderService` before the
register is imported into OpenRegister.

## Why

Hydra and the manual hand-off flow build **one OpenSpec change per branch**.
When many changes for the same app run concurrently, they all used to edit the
single `larpinq_register.json`, so the 2nd…Nth PR collided on merge. Disjoint
fragment files never conflict, so same-app concurrency is unbounded.

## How to add a fragment

When a change introduces new schemas or paths, drop a file named after the
change, e.g. `register.d/<change-slug>.json`:

```json
{
  "components": {
    "schemas": {
      "MyNewSchema": { "type": "object", "properties": { } }
    }
  },
  "paths": { }
}
```

## Merge semantics

- Associative objects (`components.schemas`, `paths`) **union by key**.
- List arrays (`required`, `enum`, examples) **concatenate**.
- Scalars in a fragment **overwrite** the base.
- Two changes declaring the **same** schema name still logically collide
  (last-merged wins) — that is a genuine spec conflict (ADR-012 dedup), not a
  file conflict. Grep existing schema names first.

Do **not** edit `larpinq_register.json`, `appinfo/info.xml`, or `CHANGELOG.md`
in a build branch — the single version bump is owned by the apply/release step.

The merged fragment signature is folded into the OpenRegister import version
(`<ver>+frag.<hash>`) so the version-gated import re-runs whenever fragments
change.

## Four literals in `larpinq_register.json` are FROZEN on the old app id

The app id moved `larpingapp` -> `larpinq`. These did **not**, and must never be
"tidied up" — JSON has no comments, so the warning lives here:

| literal | why it is frozen |
| --- | --- |
| `x-openregister.app: "larpingapp"` | OpenRegister's import handler uses this as the register **slug** (`$xOpenregister['app'] ?? $appId`) when auto-creating an `application`-typed configuration. |
| the `components.registers.larpingapp` **key** | the register slug again. |
| `registers.larpingapp.slug: "larpingapp"` | the slug OpenRegister matches an existing register by. |
| `registers.larpingapp.folder: "Open Registers/LarpingApp"` | the Files folder that already holds this register's documents on every existing install. |

Renaming any of them does not rename anything. OpenRegister matches registers by
slug, so a renamed slug simply fails to find the register holding every existing
character, player, item and event — the importer then creates a fresh **empty**
register beside it and every stored object is orphaned. Nothing errors and no
test fails; the data just stops being visible. The slug is internal and no user
ever sees it. The same applies to the folder: renaming it makes every existing
document invisible (that exact defect shipped during the docudesk rename).

The schema slugs `larping_item`, `larping_event` and `larping_attendance` are
frozen for the same reason — they are persisted on every stored object. Note
they carry the bare word `larping`, not the app id, so they were never in scope.
