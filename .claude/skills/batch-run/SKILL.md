---
name: batch-run
description: Execute a batch of GitHub issues using parallel agents in isolated worktrees. Use when the user wants to run a planned wave of work. Expects issue numbers and branch names as arguments. Example usage - /batch-run Wave 4 - #14 (Filament CRUD tests), #17 (AI agent tests)
disable-model-invocation: true
---

# Batch Run

Execute a planned batch of GitHub issues using parallel agents in isolated git worktrees.

**Arguments:** $ARGUMENTS (wave name and issue numbers)

## Pre-flight Checks

### 1. Validate Arguments

Parse `$ARGUMENTS` to extract:
- Wave name (e.g., "Wave 4")
- Issue numbers (e.g., #14, #17)

If arguments are missing or unclear, ask the user to specify which issues to run.

### 2. Fetch Issue Details

For each issue number, fetch the full details:

```bash
gh issue view <number> --json number,title,body,labels
```

### 3. Verify Clean State

```bash
git status --short
git branch --show-current
```

Ensure we're on `master` with no uncommitted changes. Warn if not.

### 4. Pull Latest

```bash
git pull origin master
```

## Execution

### 5. Launch Parallel Agents

For each issue in the batch, launch a Task agent with these settings:

| Setting | Value |
|---------|-------|
| `subagent_type` | `general-purpose` |
| `isolation` | `worktree` |
| `mode` | `bypassPermissions` |
| `run_in_background` | `true` (if more than 1 issue) |

**CRITICAL — Every agent prompt MUST include:**

1. The full issue body (title + acceptance criteria)
2. The branch name: `<type>/<issue#>-<description>` (e.g., `feat/14-filament-crud-tests`)
3. The commit message format: `<type>(scope): description (#issue)`
4. Project conventions from CLAUDE.md (TDD, Pest, PostgreSQL rules, etc.)
5. These worktree-specific instructions:

```
WORKTREE SETUP (do these first):
- Copy .env.example to .env: cp .env.example .env
- Generate app key: php artisan key:generate
- Set DB password in .env: sed -i 's/DB_PASSWORD=/DB_PASSWORD=admin/' .env
- Run: php artisan config:clear

GIT RULES:
- Do NOT run `git checkout master` — the worktree is already based on master
- Create your branch: git checkout -b <branch-name>
- Make atomic commits with the format: <type>(scope): description (#issue)
- After all work is done, push and create PR:
  gh pr create --base master --title "<title>" --body "<body with Closes #XX>"

TDD WORKFLOW:
- Write failing tests FIRST
- Implement code to make tests pass
- Refactor: clean up, remove duplication, improve naming
- Run: php artisan test --filter=<related> to verify
- Run: vendor/bin/phpstan analyse to check static analysis

QUALITY GATES (must pass before PR):
- php artisan test (all tests pass)
- vendor/bin/phpstan analyse (no new errors)
```

### 6. Monitor Progress

After launching all agents, wait for completion notifications. Do NOT poll or sleep.

When each agent completes:
- Check if it created a PR (look for PR URL in output)
- Note any failures or issues

### 7. Report Results

After all agents complete, present a summary:

```markdown
## Wave N Results

| Issue | Branch | PR | Status | Notes |
|-------|--------|-----|--------|-------|
| #X | feat/X-desc | #PR | Success/Failed | ... |

## Next Steps
- Review PRs: [links]
- If any failed: [what went wrong and suggested fix]
- Run `/batch-plan` to check remaining issues
```

### 8. Update Memory

Update the project state memory file with:
- Which wave was completed
- PR numbers created
- Any issues encountered
- Updated remaining issues count

## Error Handling

- If an agent fails, do NOT retry automatically — report the failure and let the user decide
- If a worktree already exists for a branch, clean it up first or use a different branch name
- If tests fail in an agent, the agent should still create the PR (marked as draft) so progress isn't lost
