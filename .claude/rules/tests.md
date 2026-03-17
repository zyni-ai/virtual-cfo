---
paths:
  - "tests/**"
  - "database/factories/**"
---

# Test Rules

## TDD: RED → GREEN → REFACTOR

1. Write failing test first
2. Implement minimal code to pass
3. Refactor — run `/simplify` on modified files, then re-run tests

## Pest Conventions

- Use `describe`/`it` blocks (not `test()`)
- Use factories for database records (never mocks for models)
- Follow existing patterns in the same test directory
- Run: `php artisan test --filter=<TestName> --compact`

## Testing Diamond (PD-012)

- ~5% static analysis (PHPStan)
- ~25% unit/architecture tests
- ~65% integration/feature tests (primary focus)
- ~5% E2E tests

## What to Test per API Endpoint

| Scenario | Status | Assert |
|----------|--------|--------|
| Happy path | 200/201 | Correct data structure AND values |
| Validation failure | 422 | Error messages and field names |
| Unauthenticated | 401 | Unauthenticated message |
| Unauthorized | 403 | `"This action is unauthorized."` message |
| Not found | 404 | Not found message |
| Empty collection | 200 | Empty `data` array, `total: 0` |
| Pagination | 200 | `meta` and `links` values |
| Filtering/sorting | 200 | Correct subset and order |
| Business rule violation | 409 | Descriptive error message |

## Factory Conventions

- Define states for common variations: `->suspended()`, `->withSubscription()`
- Use `afterCreating()` for related models — don't create them manually in tests
- `is_platform_admin` on User is a public property, NOT a DB column — set after `create()`, not as a factory attribute
- Keep factory defaults minimal — use states for test-specific setups

## Don't

- Don't replace factories with mocks to dodge DB issues
- Don't skip `describe` blocks for grouping
- Don't use `RefreshDatabase` — project uses `LazilyRefreshDatabase`
- Don't write `assertOk()`-only tests — every test must assert beyond HTTP status
- Don't test config values — test behavior, not `config('key') === 'value'`
- Don't use Reflection-based tests — test public API, not internal implementation
- Don't mock DB interactions — use real database with factories
