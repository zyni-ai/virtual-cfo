# Release Process

**Last Updated:** 2026-03-02
**Audience:** Developers, DevOps

---

## Overview

Virtual CFO uses trunk-based development with semantic versioning. All work merges into `master` via squash merge. Releases are cut by tagging `master` with a version number.

```
feature/* --> master (squash merge, CI) --> staging (auto) --> tag vX.Y.Z --> production
```

---

## Semantic Versioning

| Bump | When | Example |
|------|------|---------|
| **Patch** `vX.Y.Z+1` | Bug fixes, minor tweaks | Fix PDF parsing edge case |
| **Minor** `vX.Y+1.0` | New features, non-breaking changes | Add reports page, notifications |
| **Major** `vX+1.0.0` | Breaking changes | Major schema changes, API breaks |

---

## Cutting a Release

### Pre-release Checklist

- [ ] All PRs for this release are merged into `master`
- [ ] CI is green on `master` (syntax, pint, phpstan, security, tests, type coverage)
- [ ] No open `priority: critical` or `priority: high` issues targeting this release
- [ ] Database migrations are reviewed and reversible

### Create the Release

```bash
# 1. Ensure master is up to date
git checkout master && git pull

# 2. Preview release notes (optional — creates a draft)
gh release create vX.Y.Z --generate-notes --draft --target master

# 3. Create the release (for real)
gh release create vX.Y.Z --generate-notes --target master --title "vX.Y.Z"
```

GitHub's `--generate-notes` auto-generates release notes from merged PRs since the last tag, grouped by label categories defined in `.github/release.yml`.

### Label Categories

Release notes are grouped by PR labels:

| Label | Section in Release Notes |
|-------|--------------------------|
| `type: feature` | New Features |
| `type: bug` | Bug Fixes |
| `type: refactor` | Improvements |
| `type: docs` | Documentation |
| `type: chore`, `type: security`, `type: test` | Maintenance |

**Every PR should have a `type:` label** for proper release notes categorization.

---

## Deployment

### Deploy Workflow

The `.github/workflows/deploy.yml` workflow triggers automatically when a tag matching `v*` is pushed. Currently a placeholder — configure the deployment target when the hosting platform is decided.

### Post-deployment Steps

After deployment completes, run these commands on the production server:

```bash
# 1. Run database migrations
php artisan migrate --force

# 2. Cache configuration, routes, and views
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 3. Restart queue workers (picks up new code)
php artisan queue:restart
```

### Smoke Test

After deployment, verify:

1. Login works
2. Upload a file — PDF parsing starts
3. Dashboard loads with correct data
4. Queue worker is processing jobs

---

## Hotfix Process

For urgent production fixes:

```bash
# 1. Branch from master
git checkout master && git pull
git checkout -b hotfix/fix-critical-bug

# 2. Fix, test, push, create PR
# (same as normal development but prioritized)

# 3. After squash merge to master, tag immediately
gh release create vX.Y.Z+1 --generate-notes --target master --title "vX.Y.Z+1"
```

No long-lived release branches. Hotfixes follow the same trunk-based flow.

---

## Rollback

If a release introduces issues:

```bash
# Option 1: Re-deploy the previous tag
# (via CI/CD — trigger deploy workflow with previous tag)

# Option 2: Revert the problematic commit on master, then tag
git revert <commit-sha>
git push origin master
gh release create vX.Y.Z+1 --generate-notes --target master
```

For database rollbacks:

```bash
# Rollback the last migration batch
php artisan migrate:rollback --step=1 --force
```

---

## Quick Reference

```bash
# See what's changed since last release
gh api repos/:owner/:repo/compare/v1.0.0...master --jq '.commits | length'

# Preview release notes before creating
gh release create vX.Y.Z --generate-notes --draft --target master

# Create release
gh release create vX.Y.Z --generate-notes --target master --title "vX.Y.Z"

# List all releases
gh release list

# View a specific release
gh release view vX.Y.Z
```

---

## Related Resources

| Resource | Description |
|----------|-------------|
| [CLAUDE.md](../../CLAUDE.md) | Branching strategy and conventions |
| [AI-Assisted Development Workflow](ai-assisted-development-workflow.md) | Full development process |
| `.github/release.yml` | Release notes label configuration |
| `.github/workflows/deploy.yml` | Deployment workflow (placeholder) |

---

## Changelog

| Date | Author | Changes |
|------|--------|---------|
| 2026-03-02 | Team | Initial release process documentation |
