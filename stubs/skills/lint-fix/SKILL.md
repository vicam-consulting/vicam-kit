---
name: lint-fix
description: >-
  Run and fix all linting issues across PHP and frontend code. Activates when we are wrapping up a new feature implementation, bugfix, or other development task.
user_invocable: true
---

# Lint & Fix
run the following commands:
composer lint (runs Pint + Rector, should auto-fix some things)
npm run lint && npm run format

composer test:types
please fix any errors and re-run to verify fixes
check composer lint again

npm run lint:types
fix any issues
DO NOT IGNORE ERRORS to get the lint to pass, unless there are conflicting rules or some other issue.
Do not just do fallbacks on nullchecks.  Investigate and make the code correct.  There are only very rare cases where fields are optional and therefore you can use a fallback.

run php artisan test --parallel to make sure fixes didn't break any tests
