---
allowed-tools: Bash(gh *), Bash(git *), Bash(php *), Bash(vendor/bin/*), Bash(bash bin/*), Read, Grep, Glob, Edit, Write, Agent, ToolSearch, Skill, TaskCreate, TaskUpdate, TaskList
description: Fix a GitHub issue end-to-end with TDD
argument-hint: <issue-number>
---

Fix GitHub issue #$ARGUMENTS. For detailed sub-steps, refer to `docs/guides/ai-assisted-development-workflow.md`.

## Issue details
!`gh issue view $ARGUMENTS`

## Scope Discipline (applies to ALL steps)

If you discover unrelated issues at ANY point — while exploring, writing tests, or implementing — immediately run `gh issue create` to track them. **Never fix unrelated issues in this task. No exceptions.**

## Create Progress Checklist

Before starting, create a TaskCreate item for each step below. Update each to `completed` as you finish it. **Do NOT proceed to Step 10 (Commit) unless ALL prior items are completed.**

```
TaskCreate: "Step 0: Pre-flight — MCP tools verified"
TaskCreate: "Step 1: Understand — issue read, task type classified"
TaskCreate: "Step 2: Branch — branch created"
TaskCreate: "Step 3: Plan — approach outlined"
TaskCreate: "Step 4: TDD RED — failing tests written"
TaskCreate: "Step 5: Spec Review — tests match acceptance criteria"
TaskCreate: "Step 6: TDD GREEN — all tests pass"
TaskCreate: "Step 7: TDD REFACTOR — simplified and tests still green"
TaskCreate: "Step 8: CI Checks — pint + phpstan + tests pass"
TaskCreate: "Step 9: Verify — behavior confirmed"
TaskCreate: "Step 10: Commit — changes committed"
TaskCreate: "Step 11: Comment — issue comment posted"
```

## Implementation Steps (0–11)

These steps are the reusable unit — used by both `/fix-issue` (interactive) and `/batch-run` (parallel agents).

### Step 0: Pre-flight (HARD GATE)

Run `ToolSearch` for `mcp__laravel-boost` to verify MCP tools are available.

- **If available:** Proceed to Step 1.
- **If unavailable:** **STOP IMMEDIATELY.** Report: "MCP tools unavailable — cannot proceed." Do NOT continue.

Update TaskCreate item to `completed`.

---

### Step 1: Understand

- Read the issue fully
- Identify relevant files using Grep/Glob
- Check `docs/adr/` and `docs/modules/` for context
- Use Laravel Boost MCP tools extensively to build context. Run `ToolSearch` for `mcp__laravel-boost` to discover all available tools — do not limit yourself to a few. Use whichever tools are relevant to the task.
- Classify the task type:
  - `feature` / `fix` → TDD workflow (Steps 4–7)
  - `refactor` → Tests-first (run existing tests, refactor, re-run)
  - `docs` / `config` → Direct implementation (skip Steps 4–7)

Update TaskCreate item to `completed`.

---

### Step 2: Branch

Create branch: `<type>/$ARGUMENTS-<short-description>` from latest `main`. Types: feat, fix, refactor.

Update TaskCreate item to `completed`.

---

### Step 3: Plan

Before writing code, outline the approach. Use MCP tools to verify assumptions (`search-docs`, `database-schema`, `tinker`, `list-routes`). For non-trivial work, use Plan Mode.

Update TaskCreate item to `completed`.

---

### Step 4: TDD RED

Write failing tests for ALL acceptance criteria in the issue:

- Use Pest `describe`/`it` blocks (never `test()`)
- Use factories for database records (never mocks for models)
- Every mutation test needs 3 assertions: status + response body + side effect
- Follow existing patterns in the same test directory
- Run `php artisan test --filter=<TestName> --compact` to confirm they **fail**

**Gate:** Tests must exist AND fail before proceeding. If tests pass immediately, they are not testing new behavior — review the test logic.

Update TaskCreate item to `completed`.

---

### >>>>>> HARD GATE: Do NOT proceed to Step 6 until Step 5 is complete <<<<<<

### Step 5: Spec Review

Review your tests against the issue's acceptance criteria. This is the cheapest place to catch gaps — no implementation code exists yet.

**For each acceptance criterion in the issue:**
1. Find the corresponding test in your test file
2. Verify the test asserts the right behavior (status + response + side effect for mutations)
3. If a criterion has no test: **write the missing test now**

**Checklist:**
- [ ] Every acceptance criterion has at least one test
- [ ] Authorization tests cover both allow AND deny paths
- [ ] Validation tests cover rejection of invalid data
- [ ] Edge cases from the issue are covered

**Gate (max 2 rounds):** If after 2 rounds of review you're still finding gaps, accept current coverage and note the gaps in Step 11's issue comment.

Update TaskCreate item to `completed`.

---

### >>>>>> HARD GATE: Do NOT proceed to Step 7 until ALL tests pass <<<<<<

### Step 6: TDD GREEN

Write minimal implementation to pass all tests. Run tests after **each change**:

```bash
php artisan test --filter=<TestName> --compact
```

**If tests keep failing after 3 different approaches:** STOP. Report which tests fail, what you tried, and the exact error output. Do not keep trying.

Update TaskCreate item to `completed`.

---

### Step 7: TDD REFACTOR

1. Review modified files for:
   - Duplicated logic that can be extracted
   - Overly complex methods that can be simplified
   - Naming that doesn't communicate intent
   - Unused imports or dead code
2. Run `/simplify` on modified PHP files (invoke the `simplify` skill)
3. Re-run tests to confirm still green:
   ```bash
   php artisan test --filter=<TestName> --compact
   ```
4. If tests break after refactoring: revert the refactoring change, try a different simplification

Update TaskCreate item to `completed`.

---

### >>>>>> HARD GATE: Do NOT proceed to Step 10 until Step 8 passes <<<<<<

### Step 8: CI Checks

Run the project-wide CI verification:

```bash
bash bin/ci-check.sh --filter=<TestName>
```

- If **OVERALL: PASS** — proceed to Step 9
- If **Pint: FAIL** — run `vendor/bin/pint` to auto-fix, then re-run
- If **PHPStan: FAIL** — fix reported errors (max 3 attempts), then re-run
- If **Tests: FAIL** — diagnose and fix (max 3 attempts), then re-run

**After 3 full CI cycles that still fail:** STOP and report the state.

Skip this step entirely for non-PHP changes (docs, config, markdown).

Update TaskCreate item to `completed`.

---

### Step 9: Verify

Confirm the implementation works end-to-end:

1. Review test output — are ALL tests passing (not just the new ones)?
2. Use Laravel Boost MCP tools to verify the implementation. Run `ToolSearch` for `mcp__laravel-boost` to see all available tools — use whichever are relevant (routes, schema, tinker, config, logs, etc.). Do not limit yourself to a fixed list.
3. Check that no unrelated tests broke

Update TaskCreate item to `completed`.

---

### Step 10: Commit

**Pre-commit check:** Run `TaskList` and verify ALL steps 0–9 are marked `completed`. If any are not, go back and complete them.

- Stage specific files only (never `git add -A`)
- One atomic commit per task
- Commit message: `<type>(scope): description (#$ARGUMENTS)`

Update TaskCreate item to `completed`.

---

### Step 11: Comment

Add structured implementation summary to issue #$ARGUMENTS:

```bash
gh issue comment $ARGUMENTS --body "## Implementation Summary
- **Changes:** <what was changed and why>
- **Files modified:** <list of files>
- **Tests:** <tests added/modified, or 'N/A — config/docs change'>
- **CI checks:** Pint: Pass | PHPStan: Pass | Tests: Pass
- **Gaps or concerns:** <any issues found, retry limits hit, or 'None'>
- **Related issues created:** <links to any new issues, or 'None'>"
```

Update TaskCreate item to `completed`.

---

## Delivery Step (interactive only — skip if running as part of a batch)

12. **Push & PR** — Push branch and create PR. Include `## Summary`, `## Test plan`, and `Closes #$ARGUMENTS` in the PR body. Add a `type:` label.
