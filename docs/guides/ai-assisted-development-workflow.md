# AI-Assisted Development Workflow

**Last Updated:** 2026-02-24
**Audience:** Developers

---

## Overview

A structured, step-by-step process for using Claude Code to work on GitHub issues and implementation tasks in the Virtual CFO codebase. This workflow ensures consistent quality, traceability, and adherence to project standards across all AI-assisted development.

These instructions apply to ALL implementation tasks automatically. Claude should follow this process without waiting for user input between tasks.

> **Tools:** This process references Laravel Boost MCP tools (`search-docs`, `database-schema`, `database-query`, `tinker`, `list-routes`). See [CLAUDE.md](../../CLAUDE.md) for tool descriptions. Use them extensively — do not skip them in favor of assumptions.

---

## Prerequisites

- [ ] Claude Code CLI installed and configured
- [ ] Laravel Boost MCP tools available (`search-docs`, `database-schema`, `database-query`, `tinker`, `list-routes`)
- [ ] GitHub CLI (`gh`) authenticated
- [ ] Local PostgreSQL database running
- [ ] Project dependencies installed (`composer install`)

---

## Task Execution Process

When working on GitHub issues or implementation tasks, follow these steps sequentially for each task:

### Step 1: Understand

- Read the GitHub issue fully: `gh issue view <#>`
- Classify the task type — this determines the workflow for Step 2:

  | Type | Examples | Step 2 Workflow |
  |------|----------|-----------------|
  | `feature`, `fix` | New resource, bug fix | TDD (Red → Green → Refactor) |
  | `refactor` | Restructure services, extract class | Tests exist → Refactor → Tests pass |
  | `docs`, `config`, `chore` | Annotations, config changes, CI | Direct implementation (no tests needed) |

- Explore affected files and their existing tests
- Identify blast radius — which files, routes, or tests will this change touch?
- Use MCP tools to build context before writing any code:
  - `search-docs` — look up relevant Laravel/Pest patterns for the task
  - `database-schema` — review affected tables if the task involves models or migrations
  - `list-routes` — check current route definitions if the task involves endpoints or middleware

### Step 2: Implement

Before writing code, use MCP tools to verify assumptions:
- `search-docs` — confirm the correct Laravel/Pest API for what you're about to implement
- `tinker` — test snippets or expressions before embedding them in code
- `database-query` — verify existing data or relationships if relevant

Follow the workflow based on task type (classified in Step 1):

#### For `feature` / `fix` tasks — use TDD

1. **RED:** Write a failing test → run it → confirm it fails
2. **GREEN:** Write minimal code to pass → run it → confirm it passes
3. **REFACTOR:** Clean up → run tests → confirm still green
4. Repeat the cycle for each behavior/requirement in the issue

#### For `refactor` tasks — tests-first safety net

1. Run existing tests for the affected area to confirm they pass
2. Make the refactoring change
3. Run the same tests again to confirm nothing broke

#### For `docs` / `config` / `chore` tasks — direct implementation

1. Make the change directly
2. Run any relevant validation

### Step 3: CI Checks (run in parallel, once)

Run both checks simultaneously:

```bash
vendor/bin/pint --test                        # Verify formatting
vendor/bin/phpstan analyse --memory-limit=512M # Static analysis
```

- If Pint fails: run `vendor/bin/pint` to fix, then re-run Step 3
- If PHPStan fails: fix the reported issues, then re-run Step 3
- Once both pass, continue to Step 4
- Skip this step for tasks that don't modify PHP files (e.g., `.env`, markdown, CI config)

### Step 4: Review & Test Loop

Run 4a → 4b → 4c in a loop until everything is clean.

#### Step 4a: Code Review

- Review against: the GitHub issue requirements, code quality, pattern consistency, edge cases

#### Step 4b: Run Affected Tests

- For `feature`/`fix`/`refactor` tasks:
  ```bash
  php artisan test --filter=<affected>
  ```
- For `docs`/`config`/`chore` tasks: run the relevant validation command instead
- Use MCP tools to verify beyond tests:
  - `list-routes` — confirm route changes are registered correctly
  - `database-query` — verify data integrity if migrations or seeders were changed
  - `tinker` — spot-check model relationships or scopes

#### Step 4c: Fix or Create Issue

- **Issue related to current task:** Fix it → go back to Step 4a
- **Issue NOT related to current task:** Do NOT ignore it. Do NOT fix it in this task. ALWAYS create a GitHub issue:
  ```bash
  gh issue create --title "<type>: <description>" --body "<details of the finding>"
  ```
  Then continue the loop — this is non-negotiable.
- **Everything is clean:** Exit loop → continue to Step 5

### Step 5: Commit

- Verify all checks actually passed — don't assume
- Stage specific files only (never `git add -A`)
- One atomic commit per task — each commit should represent a single complete, working change
- Commit only AFTER Step 4 loop is fully clean

### Step 6: Comment on GitHub Issue

Add a structured comment to the GitHub issue for traceability:

```bash
gh issue comment <#> --body "## Implementation Summary
- **Changes:** <what was changed and why>
- **Files modified:** <list of files>
- **Tests:** <tests added/modified, or 'N/A — config/docs change'>
- **Review findings:** <any issues found and fixed during Step 4, or 'Clean'>
- **Related issues created:** <links to any new issues from Step 4c, or 'None'>"
```

### Step 7: Next Task

- Move to the next task immediately without waiting for user input
- If the next task is unrelated to the current one, run `/clear` to avoid context pollution
- Repeat from Step 1

---

## After All Tasks Complete

1. Run final verification across all changes:
   ```bash
   php -d memory_limit=512M artisan test --filter=<affected>  # Affected tests only
   vendor/bin/phpstan analyse --memory-limit=512M              # Full static analysis
   ```
2. If failures: fix, re-run — do NOT push with failing checks
3. Push and create PR — PR must reference all completed issues (e.g., "Closes #1, Closes #2")

---

## Key Principles

- **Sequential execution** — One task at a time, no parallel implementation
- **No waiting** — Don't pause for user input between tasks
- **Classify first** — Every task gets a type in Step 1; the type determines the workflow
- **Use MCP tools** — Always use `search-docs`, `database-schema`, `database-query`, `tinker`, and `list-routes` to build context and verify work. Do not rely on assumptions when a tool can give you the answer
- **Scope discipline** — Never fix unrelated issues in the current task. ALWAYS create a GitHub issue for them — no exceptions
- **Quality gates** — Never skip CI checks or the review & test loop before committing
- **Commit = verified** — Every commit represents code that passes all checks, review, and tests
- **Traceability** — Every GitHub issue gets a structured comment with what was done
- **Full suite = final gate** — Run once after all tasks complete, before push

---

## Best Practices

### Quality Over Speed (CRITICAL)

- **Never replace factories with mocks to dodge DB/test issues** — Mocks hide real bugs
- **Never misdiagnose errors to move faster** — Investigate root causes fully before claiming something is "pre-existing" or "unrelated"
- **Be patient with slow tools** — Composer on Windows takes 3-5 min. Wait for proper tooling instead of manual workarounds
- **Don't cut corners** — It's more important to do it right than to do it fast
- **Investigate, don't work around** — When something fails, understand WHY before trying a different path

---

## Troubleshooting

### Problem: Tests fail with wrong DB password

**Symptoms:** `SQLSTATE[08006] FATAL: password authentication failed for user "postgres"`

**Cause:** `phpunit.xml` env values override `.env.testing`. CI uses `postgres` as the password, but your local PostgreSQL may use a different password.

**Solution:**
1. Change `DB_PASSWORD` in `phpunit.xml` to your local password for testing
2. **IMPORTANT:** Revert to `postgres` before committing — forgetting to revert breaks CI

### Problem: MCP tools not available

**Symptoms:** `search-docs`, `tinker`, etc. are not recognized

**Cause:** Laravel Boost MCP server is not configured

**Solution:** Ensure Laravel Boost is configured in your Claude Code MCP settings. See [CLAUDE.md](../../CLAUDE.md) for tool descriptions.

---

## Related Resources

| Resource | Description |
|----------|-------------|
| [CLAUDE.md](../../CLAUDE.md) | Project-level AI instructions, commands, and coding standards |
| [Testing Best Practices](testing-best-practices.md) | Testing standards and conventions |

---

## Changelog

| Date | Author | Changes |
|------|--------|---------|
| 2026-02-24 | Team | Initial guide — adapted from TMS for Virtual CFO project |
