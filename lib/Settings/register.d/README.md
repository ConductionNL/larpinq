# Register fragments (ADR-037)

Each `*.json` file in this directory is deep-merged over the monolithic
`lib/Settings/larpingapp_register.json` by `ConfigFileLoaderService` before the
register is imported into OpenRegister.

## Why

Hydra and the manual hand-off flow build **one OpenSpec change per branch**.
When many changes for the same app run concurrently, they all used to edit the
single `larpingapp_register.json`, so the 2nd…Nth PR collided on merge. Disjoint
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

Do **not** edit `larpingapp_register.json`, `appinfo/info.xml`, or `CHANGELOG.md`
in a build branch — the single version bump is owned by the apply/release step.

The merged fragment signature is folded into the OpenRegister import version
(`<ver>+frag.<hash>`) so the version-gated import re-runs whenever fragments
change.
