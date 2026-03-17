---
allowed-tools: Bash(gh *), Bash(git *), Read, Grep, Glob
description: Scan open GitHub issues, group into prioritized batches, and present a wave plan
---

# Batch Plan

Scan open GitHub issues, group them into prioritized batches by theme, and present an execution plan.

## Pre-computed context
- Open issues: !`gh issue list --state open --limit 50 --json number,title,labels`
- Current branch: !`git branch --show-current`

## Steps

### 1. Categorize Issues

Group issues by theme using labels and content:

| Category | Label patterns | Batch together? |
|----------|---------------|-----------------|
| Tests | `type: test` | Yes — no prod code changes |
| Features (infra) | `module: infra` | Yes — independent |
| Features (UI) | `module: filament` | Yes — Filament-specific |
| Bug fixes | `type: fix` | Yes — quick wins |
| Blocked | `status: blocked` | No — skip |

### 2. Prioritize Batches

1. `priority: high` issues first
2. Test-only batches before feature batches
3. Independent features before features with dependencies
4. Blocked issues last

### 3. Present the Plan

Output format:

| Batch | Issues | Theme | Branch | Rationale |
|-------|--------|-------|--------|-----------|
| Wave N | #X, #Y | ... | feat/wave-N-... | ... |

### 4. Confirm with User

Ask the user to confirm or adjust before proceeding.
