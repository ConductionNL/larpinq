# Stabilise `type:'wiki'` — typed config fields + validation

## Why

`type:'wiki'` (`CnWikiPage`) is fully implemented at the renderer level — it lives in `defaultPageTypes`, has a 16-prop API, and is validated for `register`+`schema` in `validateTypeConfig`. But every other config field (`contentField`, `titleField`, `idParam`, `treeField`, `sidebarRegister`, `sidebarSchema`, `sidebarTitleField`, `emptyText`, `emptyDescription`, `emptyBodyText`, `emptyBodyDescription`) is undocumented in the JSON Schema. Consumers rely on `additionalProperties:true` to smuggle them through and the validator never catches `contentFeild` typos.

The opencatalogi triage ([opencatalogi#636](https://github.com/ConductionNL/opencatalogi/pull/636)) flagged `ArticleDetailView` as "could be type:'wiki' once that page-type stabilises across the fleet". Stabilisation here means:

1. **Discoverability** — schema declares the typed shape so IDE completion + hover docs work.
2. **Validation** — typed errors with JSON-pointer paths on typos / wrong types.
3. **Cross-fleet confidence** — consumers can safely flip wiki-shaped customs without worrying about silent prop drift.

## What changes

1. **Schema** — add typed `wikiConfig` properties under `config` for `type:'wiki'` pages: the 11 string-typed fields the `CnWikiPage` component already accepts, each with a description that points at the matching prop.
2. **Validator** — extend the `case 'wiki':` branch of `validateTypeConfig` to type-check each known field as a string when present. Unknown keys pass for forward-compat.
3. **Schema version** — bump 2.0.0 → 2.4.0 (sequential to #294/#295/#296).
4. **Docs** — extend `docs/components/cn-wiki-page.md` with a "Manifest config reference" section mirroring the prop table for the typed config shape.
5. **Tests** — validator-level coverage (omitted / typed / wrong-type / unknown-key).

## Non-goals

- Changing `CnWikiPage`'s prop API.
- Authoring routes (article editor, category manager — those stay `type:'custom'`; lib gap for rich-text widget tracked separately).
- Tree-sidebar widget for non-wiki pages.

## Consumer impact

Unblocks the wiki-shaped customs across the fleet:
- opencatalogi `ArticleDetailView` (knowledge-base article reader).
- pipelinq `ArticleDetailView` (kennisbank article reader — same shape).
- decidesk article reader (if/when it lands).

Each flips from `type:'custom'` to `type:'wiki'` + typed `config.contentField` / `config.titleField` / etc.

## References

- [nextcloud-vue#277](https://github.com/ConductionNL/nextcloud-vue/issues/277)
- `src/components/CnWikiPage/CnWikiPage.vue` — the 16-prop component this stabilises.
- `validateTypeConfig` `case 'wiki':` — existing register/schema validation.
