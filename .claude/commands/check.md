---
description: Run the larpinq quality suite (PHP gate, ESLint, Stylelint, spec and translation checks)
---

Run the quality checks from the repository root and report each result. Do not change code to silence a check unless asked.

1. PHP gate (lint, phpcs, phpmd, psalm, phpstan, all PHPUnit tests): `composer check:strict`
2. Frontend lint: `npm run lint` and `npm run stylelint`
3. Manifest, register and strict-JSON validation: `npm run check:specs`
4. Translations: `npm run test:l10n` and `npm run check:l10n-js`

Needs `composer install` and `npm ci` first. For a single PHP tool use `composer phpcs`, `composer phpmd`, `composer psalm` or `composer phpstan`. Auto-fixers: `composer phpcs:fix`, `composer cs:fix`, `npm run lint-fix`, `npm run stylelint-fix`.

$ARGUMENTS
