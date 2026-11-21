---
description: Run the larpinq PHP and frontend unit tests
---

Run the unit tests from the repository root and report the results.

1. PHP: `composer test:unit` (PHPUnit with `phpunit.xml`: `tests/unit` and `tests/integration`). Needs `composer install`.
2. Frontend: `npm run test:unit` (Vitest with `vitest.config.js`, specs in `tests/vitest/`). Needs `npm ci`.

To run one PHP test file: `./vendor/bin/phpunit --configuration phpunit.xml <path>`. To run one Vitest file: `npx vitest run <path>`.

$ARGUMENTS
