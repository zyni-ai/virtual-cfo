---
name: batch-plan
description: Scan open GitHub issues, group them into prioritized batches by kind, and present a wave execution plan. Use when the user wants to plan the next wave of work, review remaining issues, or organize tasks into batches.
disable-model-invocation: true
tools: Read, Glob, Grep, Bash
---

# Batch Plan

Scan open GitHub issues, group them into prioritized batches by theme, and present an execution plan.

## Workflow

### Step 1: Gather Context

Read the project memory to understand what waves have been completed:

```bash
cat ~/.claude/projects/D--Code-virtual-cfo/memory/project-state.md
```

### Step 2: Fetch Open Issues

```bash
gh issue list --state open --limit 50 --json number,title,labels,body
```

### Step 3: Categorize Issues

Group issues by theme using labels and content:

| Category | Label patterns | Batch together? |
|----------|---------------|-----------------|
| Tests | `type: test` | Yes — no prod code changes |
| Features (infra) | `module: infra` | Yes — independent |
| Features (UI) | `module: filament` | Yes — Filament-specific |
| Features (pipeline) | `module: imports`, `module: export` | Yes — data flow |
| Bug fixes | `type: fix` | Yes — quick wins |
| Blocked | `status: blocked` | No — skip |

### Step 4: Prioritize Batches

Order batches by:
1. `priority: high` issues first
2. Test-only batches before feature batches (tests validate existing code)
3. Independent features before features with dependencies
4. Low priority and blocked issues last

### Step 5: Present the Plan

Output format:

```markdown
## Remaining Issues (N open)

| # | Title | Type | Priority | Status |
|---|-------|------|----------|--------|
| ... | ... | ... | ... | ... |

## Proposed Batches

| Batch | Issues | Theme | Worktree branch | Rationale |
|-------|--------|-------|-----------------|-----------|
| Wave N | #X, #Y | ... | feat/wave-N-... | ... |
| Wave N+1 | #A, #B | ... | feat/wave-N+1-... | ... |

## Recommended Next Action
Start with Wave N: [description]. Run `/batch-run` with the wave details.
```

### Step 6: Confirm with User

Ask the user to confirm or adjust the plan before proceeding.
