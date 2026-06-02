# Manifest fragments (ADR-037)

Each `*.json` file in this directory is merged onto the bundled
`src/manifest.json` by `mergeManifestFragments()` in `src/main.js` (via
webpack `require.context`) at build time.

## Why

So that concurrent same-app builds add their pages/menu entries in their own
fragment file instead of editing the shared `manifest.json`, which serialised
same-app builds on every merge conflict.

## How to add a fragment

When a change introduces new UI pages or menu entries, drop a file named after
the change, e.g. `manifest.d/<change-slug>.json`:

```json
{
  "pages": [
    { "id": "MyPage", "type": "list", "route": "MyPage", "...": "..." }
  ],
  "menu": [
    { "id": "MyPage", "label": "My Page", "icon": "Cog", "route": "MyPage", "order": 110 }
  ]
}
```

## Merge semantics

- `pages` and `menu` arrays from every fragment **concatenate** onto the base.
- Other top-level manifest keys come from the base `manifest.json`.

`_placeholder.json` is an empty fragment that guarantees `require.context` has
at least one match so the build never fails on an otherwise-empty directory. Do
not delete it.

Do **not** edit `src/manifest.json` in a build branch.
