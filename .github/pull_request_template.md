## Summary
<!-- 1-3 bullets describing what this PR does -->

## Related Issue
Closes #

## Type of Change
- [ ] `feat` - New feature
- [ ] `fix` - Bug fix
- [ ] `refactor` - Code improvement (no behavior change)
- [ ] `test` - Tests only
- [ ] `docs` - Documentation
- [ ] `chore` - Maintenance, dependencies

## Checklist
- [ ] Branch follows naming: `<type>/<issue#>-<description>`
- [ ] Commits follow format: `<type>(scope): message (#issue)`
- [ ] Tests added/updated for changes
- [ ] `vendor/bin/pint --dirty` run
- [ ] All new models have factories
- [ ] Migrations are reversible
- [ ] Encrypted fields handled properly (no plaintext in logs/exports)

## Test Results
```
php artisan test --filter=<related> --compact
# Paste output here
```
