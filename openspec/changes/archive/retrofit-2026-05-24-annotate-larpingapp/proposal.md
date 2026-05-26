# Retrofit — annotate larpingapp against existing specs

## Why

larpingapp has 13 PHP service/controller/listener/settings files in lib/ that implement existing capability REQs but carry no `@spec` PHPDoc tags. ADR-003 §Spec traceability mandates these tags so that every method body can be traced back to the requirement it satisfies. Without the tags the Hydra retrofit playbook can't tell implemented-but-untagged from genuinely-unimplemented.

This change retroactively annotates 114 methods across 13 files against 101 REQs in 10 capabilities (admin-settings, character-management, dashboard, deep-link-registration, events-players, game-mechanics, object-service, pdf-export, register-config-json, rpg-system).

## What Changes

- Adds `@spec openspec/changes/retrofit-2026-05-24-annotate-larpingapp/tasks.md#task-N` tags to 114 methods in lib/.
- Adds file-docblock `@spec` tags to 13 PHP files.
- No code logic changes. No spec deltas (all REQs already exist in `openspec/specs/`).

## Source

`openspec/coverage-report.md` + `openspec/coverage-report.json` generated 2026-05-24 (Bucket 1 high-confidence matches only; 8 needs_review entries deferred).

See [retrofit playbook](../../../.github/docs/claude/retrofit.md).
