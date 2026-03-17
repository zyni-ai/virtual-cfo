# Agent Instructions

These instructions are auto-loaded for every subagent. Follow them exactly.

---

## Hard Gate: MCP Pre-flight (Step 0)

**Before ANY other work**, verify Laravel Boost MCP tools are available:

```
Run: ToolSearch for "mcp__laravel-boost"
```

- If tools respond: proceed
- If tools are unavailable: **STOP IMMEDIATELY**. Report: "MCP tools unavailable — cannot proceed." Do NOT continue development without them.

**No retries.** MCP availability is a binary gate.

---

## Progress Tracking Protocol

At the start of every task, create a TaskCreate checklist with one item per workflow step. Update each item to `completed` as you finish it. **Do NOT proceed to the commit step unless ALL prior items are completed.**

Example:
```
TaskCreate: "Step 0: MCP Pre-flight"
TaskCreate: "Step 1: Understand issue"
TaskCreate: "Step 2: Branch"
... (one per step)
```

Update each with `TaskUpdate` as you complete it. If a step fails and you cannot resolve it within retry limits, update the task with the failure reason and STOP.

---

## Retry Limits & Escape Hatches

When a step fails, diagnose first: **is this an environment error or a code error?**

- **Environment error** (wrong DB credentials, missing table, MCP down, composer not installed): **STOP and report.** Do not attempt code fixes for environment problems.
- **Code error** (test fails, PHPStan error, validation issue): Fix and retry up to the limit below.

| Gate | Max Retries | On Limit Reached |
|------|-------------|------------------|
| MCP Pre-flight | 0 | STOP, report |
| Worktree env setup | 1 | STOP, report env issue |
| PHPStan fixes | 3 | STOP, report remaining errors with file:line |
| Test failures (GREEN phase) | 3 | STOP, report which tests fail and what approaches were tried |
| Spec review rounds | 2 | Accept current coverage, note gaps in issue comment |
| CI check full cycle | 3 | STOP, report state |
| Pint formatting | 2 | STOP, report conflict |

**After reaching any limit:** STOP and report. Include:
1. What failed (exact error output)
2. What was tried (each attempt)
3. Your diagnosis (environment vs code, root cause theory)

**Never** try creative workarounds after hitting a limit. Report and let the orchestrator or user decide.

---

## Hooks Awareness

These hooks run automatically on every file edit — you do NOT need to run them manually:

| Hook | Trigger | What it does |
|------|---------|-------------|
| `format-php.sh` | After Edit/Write of `.php` files | Auto-runs Pint formatting |
| `phpstan-check.sh` | After Edit/Write of non-test `.php` files | Runs PHPStan level 6 |
| `protect-files.sh` | Before Edit/Write | Blocks changes to `.env`, `composer.lock`, `phpunit.xml`, `docs/schema/*.sql` |
| `block-dangerous-commands.sh` | Before Bash | Blocks `rm -rf`, `git reset --hard`, force push to main |

**Do not panic** if you see auto-formatting changes after editing PHP. That is the Pint hook. Do not undo these changes.

The CI check in Step 8 (`bash bin/ci-check.sh`) is **project-wide verification** — it runs the full suite, not per-file. This is separate from hooks and still required before commit.

---

## Worktree Environment Setup

When working in an isolated worktree, complete these steps **before** starting implementation:

```bash
# 1. Install dependencies (allow 3-5 min on Windows)
composer install --no-interaction

# 2. Copy environment file
cp .env.example .env

# 3. Generate application key
php artisan key:generate

# 4. Set ALL database credentials in .env (3 connections required):
#    DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD         (healthcheck)
#    SECOND_DB_HOST, SECOND_DB_DATABASE, SECOND_DB_USERNAME, SECOND_DB_PASSWORD  (central)
#    THIRD_DB_HOST, THIRD_DB_DATABASE, THIRD_DB_USERNAME, THIRD_DB_PASSWORD      (infirmary)

# 5. Clear config cache
php artisan config:clear

# 6. Do NOT run `git checkout main` — the worktree is already based on main
```

**Test databases** (defined in `phpunit.xml`): `virtual_cfo_test`. If tests fail with "unknown database", these need to be created in PostgreSQL first — this is an environment error, not a code error.

---

## Key Coding Rules

These are the most critical rules from `.claude/rules/`. The full rule files auto-load based on file path when you edit files — consult them for complete details.

### Validation
- **Always** use Form Request classes — never `$request->validate()` inline
- Authorize via Form Request `authorize()` method or Policy

### Tests
- Use Pest `describe`/`it` blocks — never `test()`
- Use factories for database records — never mock models
- Every mutation test needs 3 assertions: status + response body + side effect
- Never write `assertOk()`-only tests

### Response Status Codes
- 200: GET/PUT/PATCH success
- 201: POST creation success
- 204: DELETE success (no body)
- 422: Validation errors only
- 403: Unauthorized (never "Forbidden.")
- 409: Business rule violations

---

## Structured Completion Report

When your task is complete (or you've hit a retry limit and stopped), output this report:

```
## Agent Completion Report
- **Status:** Success | Partial | Failed
- **Issue:** #<number> — <title>
- **Branch:** <branch-name>
- **Files modified:** <list>
- **Tests:** <added/modified count> | All passing: Yes/No
- **CI checks:** Pint: Pass/Fail | PHPStan: Pass/Fail | Tests: Pass/Fail
- **Steps completed:** <list of completed TaskCreate items>
- **Steps skipped/failed:** <list with reasons>
- **Gaps or concerns:** <anything the reviewer should know>
```

This format allows the batch orchestrator to parse results and determine next actions.
