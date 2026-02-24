# Virtual CFO - Zysk Technologies

## Commands

```bash
php artisan test --compact                    # Full test suite
php artisan test --filter=<name>              # Specific tests
vendor/bin/phpstan analyse                    # Static analysis (level 6)
composer audit                                # Security vulnerability check
php artisan migrate:fresh --seed              # Reset database
```

## MCP Tools (Laravel Boost)

- `search-docs` - Search Laravel/Pest docs for version-specific patterns (Laravel 12, Pest 4)
- `database-schema` - Review existing tables before creating migrations
- `database-query` - Run queries to verify data/relationships
- `tinker` - Test code snippets and verify implementations
- `list-routes` - Check existing routes

Use `search-docs` before implementing non-trivial Laravel features.

## Workflow

**Cycle:** Explore → Plan → Code → Commit

1. **Explore** - Read relevant files, understand context. Don't write code yet.
2. **Plan** - Use Plan Mode for non-trivial work. Create issue via `gh issue create`.
3. **Code** - TDD: Write failing tests → Implement → Refactor. Be explicit about TDD to avoid mock implementations.
4. **Verify** - Tests are the verification. Run `php artisan test --filter=<related>` and ensure all pass before committing.
5. **Commit** - At natural breakpoints: after tests written, after tests pass, after PR ready.

**Branch:** `<type>/<issue#>-<description>` (e.g., `feat/42-import-parser`)
**Commit:** `<type>(scope): description (#issue)`
**Types:** `feat`, `fix`, `refactor`, `test`, `docs`, `chore`
**PR:** `gh pr create` — must include "Closes #XX"

**TDD (IMPORTANT):** RED → GREEN → REFACTOR. Don't skip the refactor step — clean up code, remove duplication, improve naming after tests pass.

**Context:** Use `/clear` between unrelated tasks to avoid context pollution.

## Branching Strategy (Trunk-Based)

```
feature/* → master (squash merge) → CD → staging/QA → tag vX.Y.Z → production
```

| Branch | Purpose | PR Target | Protection |
|--------|---------|-----------|------------|
| `master` | Trunk — staging/QA deploys from here | - | All CI + 1 review + admins |
| `feature/*`, `fix/*` | New work | `master` | None |
| `hotfix/*` | Urgent prod fixes | `master` | None |

**Merge strategy:** Always squash merge into `master`.
**CI checks required before merge:** Syntax, Pint, PHPStan, Security, Tests

## Common Mistakes (AVOID)

These patterns have caused issues — don't repeat them:

- **Don't skip REFACTOR in TDD** - After tests pass, clean up code, remove duplication, improve naming
- **Don't use inline validation** - Always use Form Request classes, never `$request->validate()`
- **Don't create mock implementations** - During TDD, write real code that passes tests
- **Don't use complex bash commands** - Pipes/loops stall on Windows; use built-in tools instead
- **Don't assume FK indexes exist** - PostgreSQL doesn't auto-create them; add explicitly
- **Don't forget to read docs first** - Check `docs/` before implementing

## Project Overview

Virtual CFO application for automating bank/credit card statement processing, account head mapping, and Tally XML export. Built for the accounts team at Zysk Technologies.

## Tech Stack

- PHP 8.4 + Laravel 12
- Filament v5 (admin panel)
- PostgreSQL (single database, JSONB for flexible schemas)
- Laravel AI SDK (`laravel/ai`) with Mistral as primary LLM provider
- Laravel Boost + Filament Blueprint
- Pest 4 (testing) + Larastan (static analysis)
- Laravel Horizon (queue monitoring)
- Maatwebsite Excel (CSV/Excel import/export)
- Spatie Activity Log (audit trail)
- Barryvdh DomPDF (PDF report generation)

## Architecture Decisions

### Database: PostgreSQL (not MongoDB)
Originally considered MongoDB for schema flexibility (different banks have different column names). Chose PostgreSQL with JSONB columns instead because:
- Filament does not officially support MongoDB — causes "database engine does not support inserting while ignoring errors"
- JSONB provides identical schema flexibility with `raw_data` column
- Single database eliminates hybrid complexity
- Full ACID transactions, GIN indexes on JSONB, relational integrity

### LLM: Laravel AI SDK (not Prism PHP)
Chose `laravel/ai` over `prism-php/prism` because:
- First-party Laravel package — guaranteed long-term support
- Native agent framework (`php artisan make:agent`)
- Built-in file attachments, queue support, structured output
- Built-in testing with `Agent::fake()` and assertions
- Prism PHP is community-maintained with less integrated features

### PDF Parsing: LLM-powered (not regex/pdfparser)
Using Mistral LLM to parse bank statements instead of smalot/pdfparser because:
- Bank statements have wildly different layouts per bank
- Regex-based parsing requires per-bank parser maintenance
- LLM handles any format — detects columns, extracts structured data
- Works for both text-based and scanned PDFs
- Cost: ~$2/1000 pages via Mistral

### Head Matching: Hybrid (rules + LLM)
Two-pass approach:
1. Rule-based matching (fast, cheap, deterministic) for known patterns
2. LLM-based matching for ambiguous transactions with confidence scores
3. Manual review for anything below confidence threshold

### Encryption
All sensitive financial data encrypted at rest using Laravel's built-in encryption (AES-256-CBC via APP_KEY). Encrypted fields: account_number, description, debit, credit, balance, raw_data.

### File Storage
PDFs stored in `storage/app/private/statements/` — never publicly accessible. Served only through authenticated Filament routes.

## Environment

- PostgreSQL required (not MySQL/SQLite)
- Pint auto-runs via hook after PHP edits — no need to run manually
- Tests use Pest with `describe`/`it` blocks — follow existing test patterns

## Model Safety Mechanisms

Laravel's safety mechanisms are enabled in non-production environments:

| Mechanism | Purpose | Fix |
|-----------|---------|-----|
| `preventLazyLoading` | Catches N+1 queries | Use `with()` for eager loading |
| `preventAccessingMissingAttributes` | Catches typos in attributes | Add missing accessors |
| `preventSilentlyDiscardingAttributes` | Catches mass assignment issues | Add to `$fillable` |

**Windows CLI (IMPORTANT):** Complex bash commands (pipes, loops) may stall. Use built-in tools instead:
- `Grep` tool instead of `grep` or `rg` commands
- `Glob` tool instead of `find` commands
- `Read` tool instead of `cat`, `head`, `tail` commands

## PostgreSQL Rules (IMPORTANT)

For new migrations, these rules are non-negotiable:

| Do | Don't |
|----|-------|
| `TEXT` | `VARCHAR(n)` |
| `TIMESTAMPTZ` | `TIMESTAMP` |
| `BIGINT GENERATED ALWAYS AS IDENTITY` | `SERIAL` |
| `JSONB` with CHECK constraint | `JSON` |
| Explicit FK indexes | Assume auto-indexing |
| Partial unique: `WHERE deleted_at IS NULL` | Table-level unique with soft deletes |

## Key Patterns

### Enums
- `ImportStatus`: pending, processing, completed, failed
- `MappingType`: unmapped, auto, manual, ai
- `MatchType`: contains, exact, regex
- `StatementType`: bank, credit_card

### AI Agents
- `StatementParser` — PDF → structured transaction data
- `HeadMatcher` — transaction descriptions → account head suggestions with confidence

### Background Jobs
- `ProcessImportedFile` — parses PDF via StatementParser agent, creates transactions
- `MatchTransactionHeads` — runs rule-based + AI matching on unmapped transactions

## Documentation

| Folder | Purpose | When to Read |
|--------|---------|--------------|
| `docs/guides/` | How-to guides (AI workflow, testing) | For step-by-step workflows |
| `docs/PLAN.md` | Project setup plan and implementation order | For project context |

### AI-Assisted Development Workflow

Follow the [AI-Assisted Development Workflow](docs/guides/ai-assisted-development-workflow.md) for all implementation tasks. It defines the 7-step process (Understand → Implement → CI Checks → Review & Test → Commit → Comment → Next Task) including TDD workflows, MCP tool usage, and quality gates.

### Testing Best Practices

Follow the [Testing Best Practices](docs/guides/testing-best-practices.md) guide when writing or reviewing tests. Key rules:

- **Testing Diamond:** ~5% static, ~25% unit/arch, ~65% integration, ~5% E2E
- **No config-value assertions** — test behavior, not `config('key') === 'value'`
- **No Reflection-based tests** — test public API, not internal implementation
- **No assertOk-only tests** — every test must assert something beyond HTTP 200
- **Filament CRUD tests** must verify form fields, table records, or visible content

## Development Notes
- Very few users (small accounts team)
- Stage-based development — start with import/parse/map/export pipeline
- Tally XML reference file to be provided later
- OCR support via Mistral handles scanned PDFs automatically
