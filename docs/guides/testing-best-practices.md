# Testing Best Practices — The Testing Diamond

> **Target ratio:** ~5% static, ~25% unit+arch, ~65% integration, ~5% E2E

This guide defines **how** to write tests for Virtual CFO.

---

## Table of Contents

1. [Test Organization](#1-test-organization)
2. [The 4-Layer Diamond](#2-the-4-layer-diamond)
3. [Integration Test Quality Standards](#3-integration-test-quality-standards)
4. [Database & Factory Patterns](#4-database--factory-patterns)
5. [Shared Helpers & Custom Expectations](#5-shared-helpers--custom-expectations)
6. [Test Execution & Performance](#6-test-execution--performance)
7. [Anti-Patterns](#8-anti-patterns)
8. [PR Review Checklist](#9-pr-review-checklist)

---

## 1. Test Organization

### Directory Structure

```
tests/
├── Feature/                    # Integration tests (~65% of suite)
│   ├── Filament/               # Filament resource tests
│   │   ├── Resources/          # CRUD tests (one file per resource)
│   │   └── ...                 # Other Filament tests (access, nav)
│   ├── Jobs/                   # Background job tests
│   └── ...                     # Other feature tests
├── Unit/                       # Unit tests (~25% of suite)
│   ├── Services/               # Service class tests
│   └── ...
├── Architecture/               # Arch tests (Pest presets)
├── Datasets/                   # Pest datasets for parameterized tests
├── Expectations.php            # Custom expect() extensions
└── Pest.php                    # Global config, helpers, beforeEach hooks
```

### File Naming

| Test Type | Convention | Example |
|-----------|-----------|---------|
| Filament CRUD | `{Resource}CrudTest.php` | `ImportedFileResourceCrudTest.php` |
| Filament other | `{Feature}Test.php` | `DashboardWidgetsTest.php` |
| Job | `{Job}Test.php` | `ProcessImportedFileTest.php` |
| Service unit | `{Service}Test.php` | `RuleBasedMatcherTest.php` |
| Architecture | `ArchTest.php` | `ArchTest.php` |

### Naming Tests

Use `describe`/`it` blocks. **Never use `test()`.** Names describe **behavior**, not implementation:

```php
// BAD: Describes implementation
it('calls the save method on the model')

// GOOD: Describes behavior
it('creates transaction records from parsed PDF data')

// BAD: Vague
it('works correctly')

// GOOD: Specific outcome
it('marks import as failed when PDF parsing returns no transactions')
```

### Group Tags

Every `describe` block must have group tags for selective execution:

```php
describe('ImportedFile CRUD', function () {
    // ...
})->group('filament', 'crud', 'module-imports');
```

Standard groups:

| Group | When to Use |
|-------|------------|
| `filament` | All Filament tests |
| `crud` | CRUD operation tests |
| `authorization` | Access control tests |
| `database` | Database constraint / migration tests |
| `jobs` | Background job tests |
| `ai` | AI agent tests |
| `module-imports` | Import/parse pipeline |
| `module-mapping` | Head mapping pipeline |
| `module-export` | Tally export pipeline |

Run selectively: `php artisan test --group=filament`

---

## 2. The 4-Layer Diamond

### Layer 1: Static Analysis (~5%)

**Tools:** PHPStan level 6, Pint, Pest type coverage, Pest arch presets
**Runs:** Automatically on every push via CI

| Metric | Threshold |
|--------|-----------|
| PHPStan | Level 6, 0 errors |
| Pint | 0 style violations |
| Type coverage | 100% |

### Layer 2: Unit + Architecture Tests (~25%)

**When to write unit tests:**
- Service classes with calculation/transformation logic (e.g., RuleBasedMatcher)
- Value objects and DTOs
- Utility/helper functions
- Any pure function (input → output, no side effects)

**When NOT to write unit tests:**
- Eloquent model methods (need real DB — use integration)
- Anything requiring >2 mocked dependencies
- Config assertions (test behavior, not config values)

**The 2-mock rule:** If you need to mock more than 2 dependencies to isolate a unit, it should be an integration test.

**`covers()` annotation:** All unit test files MUST declare what they cover:

```php
covers(RuleBasedMatcher::class);

describe('RuleBasedMatcher', function () { ... });
```

### Layer 3: Integration Tests (~65%)

This is the bulk of the suite.

| Type | Tests | Example |
|------|-------|---------|
| Filament CRUD | Form → validation → DB → redirect | `ImportedFileResourceCrudTest` |
| Jobs | Dispatch → process → side effects | `ProcessImportedFileTest` |
| AI agents | Input → agent → structured output | `StatementParserTest` |
| Database constraints | INSERT with invalid data → constraint error | constraint-specific tests |

### Layer 4: E2E / Browser Tests (~5%)

**When to write:**
- Multi-step flows (upload → parse → map → export)
- Flows crossing authentication boundaries

**When NOT to write:**
- Individual CRUD operations (covered by integration)
- Anything testable without a browser

---

## 3. Integration Test Quality Standards

### The 3-Assertion Rule

Every integration test that mutates state MUST have all three:

1. **Status assertion** — HTTP status or Livewire assertion
2. **Response body assertion** — What was returned
3. **Side effect assertion** — What changed in the database

**Good Filament example:**

```php
it('can create an account head with parent relationship', function () {
    $parent = AccountHead::factory()->create();

    livewire(CreateAccountHead::class)
        ->fillForm([
            'name' => 'Office Rent',
            'group_name' => 'Indirect Expenses',
            'parent_id' => $parent->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();                        // 1. Status

    $head = AccountHead::where('name', 'Office Rent')->first();
    expect($head)->not->toBeNull()                        // 2. Response/state
        ->and($head->parent_id)->toBe($parent->id);      // 3. Side effect
});
```

### Read-Only Tests

Tests that assert read operations don't need the side-effect assertion, but MUST assert response content:

```php
// BAD: assertOk() only
it('can list imported files', function () {
    $this->actingAs($user)->get('/admin/imported-files')->assertOk();
});

// GOOD: Asserts response content
it('can list imported files', function () {
    ImportedFile::factory()->count(3)->create();

    livewire(ListImportedFiles::class)
        ->assertCanSeeTableRecords(ImportedFile::all());
});
```

### Validation Tests

Test rejection of invalid data:

```php
it('rejects duplicate file upload by hash', function () {
    ImportedFile::factory()->create(['file_hash' => 'abc123']);

    livewire(CreateImportedFile::class)
        ->fillForm([...])
        ->call('create')
        ->assertHasFormErrors(['file' => 'unique']);
});
```

---

## 4. Database & Factory Patterns

### Always PostgreSQL

Virtual CFO uses PostgreSQL exclusively. **Never use SQLite for tests.** JSONB columns, GIN indexes, and encrypted fields only work correctly against real PostgreSQL.

The `LazilyRefreshDatabase` trait is globally configured in `tests/Pest.php`.

### Factory Best Practices

**Use factory states for common setups:**

```php
// GOOD: Factory state communicates intent
$file = ImportedFile::factory()->completed()->create();
$transaction = Transaction::factory()->unmapped()->create();

// BAD: Inline attribute overrides
$file = ImportedFile::factory()->create(['status' => ImportStatus::Completed]);
```

**Create only what the test needs:**

```php
// BAD: Creates 10 records when the test only needs 1
$files = ImportedFile::factory()->count(10)->create();

// GOOD: Creates exactly what's needed
$file = ImportedFile::factory()->create();
```

---

## 5. Shared Helpers & Custom Expectations

### Global Helpers (`tests/Pest.php`)

**When to add a new helper:** Only when the same setup is duplicated across 5+ test files.

### Custom Expectations (`tests/Expectations.php`)

**When to create a new custom expectation:** When you find yourself writing the same multi-step assertion across 3+ test files. The expectation should encode a **domain concept**.

---

## 6. Test Execution & Performance

### Local Development

```bash
# TDD loop — run specific test
php artisan test --filter=ImportedFileResourceCrudTest --compact

# Run a group
php artisan test --group=filament --compact

# Full suite
php artisan test --compact

# Profile slowest tests
php artisan test --profile

# Parallel execution
php artisan test --parallel
```

### CI Pipeline

| Job | What It Checks |
|-----|---------------|
| Syntax | PHP lint |
| Style | Pint |
| Static Analysis | PHPStan level 6 |
| Security | `composer audit` |
| Unit & Arch Tests | Unit + architecture tests |
| Feature Tests | Full feature suite + coverage |
| Type Coverage | 100% type declarations |

---

## 7. Anti-Patterns

### Anti-Pattern 1: `assertOk()` Only

Tests that assert nothing beyond "the page didn't crash" provide false confidence.

### Anti-Pattern 2: Testing Source Code via Reflection

Tests that inspect source code test **implementation**, not **behavior**.

### Anti-Pattern 3: Testing Config Values

Tests that assert `config()` values test that Laravel's config system works, not your application.

### Anti-Pattern 4: Testing Framework Behavior

Don't test that Laravel/Filament/Pest work correctly — that's their maintainers' job.

### Anti-Pattern 5: Duplicate Tests Across Layers

Don't test the same thing in both a unit test and an integration test. Choose one.

### Anti-Pattern 6: Unused Infrastructure

Dead code in test infrastructure creates confusion. If a dataset, helper, or expectation has zero consumers, delete it.

---

## 8. PR Review Checklist

When reviewing test code in a PR, verify:

- [ ] **Behavior, not implementation** — test names describe outcomes, not method calls
- [ ] **3-assertion rule** — mutation tests have status + response + side-effect
- [ ] **No `assertOk()` only** — every test asserts business logic
- [ ] **No Reflection** — no source code inspection tests
- [ ] **No config assertions** — tests verify behavior, not config values
- [ ] **Group tags applied** — every `describe` block has relevant groups
- [ ] **`covers()` on unit tests** — enables scoped mutation testing
- [ ] **Factories preferred** — no mocking models, no inline SQL
- [ ] **Only tests what's needed** — no redundant tests duplicating coverage

---

## Changelog

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-02-24 | Initial guide — adapted from TMS for Virtual CFO project |
