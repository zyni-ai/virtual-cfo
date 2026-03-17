---
paths:
  - "app/Models/**"
---

# Model Rules

## Mass Assignment

- **Read-only models** (external databases: `healthcheck`, `central`, `infirmary`): Use `$guarded = ['*']` — these models never perform mass assignment, so `$fillable` is not needed
- **Writable models** (local database): Always define `$fillable` explicitly (never use `$guarded = []`)
- `preventSilentlyDiscardingAttributes` is enabled — unfillable fields will throw

## Type Safety

- Define `$casts` for all non-string columns (booleans, dates, JSON, enums)
- `preventAccessingMissingAttributes` is enabled — typos in attributes will throw

## Relationships

- Define relationships with return types: `public function org(): BelongsTo`
- `preventLazyLoading` is enabled in non-production
- When it triggers, the fix is NOT always "add to `with()`" — check if the relation is actually needed. If not, remove the access from the Resource instead
- Only load relations the endpoint actually needs (don't over-eager)

## Conventions

- Namespace models by module: `App\Models\CompetitiveExams\ExamCategory`
- Use traits: `HasFactory`, `SoftDeletes` where applicable
- `is_platform_admin` on User is a public property, NOT a DB column — set after `create()`, not as factory attribute

## Query Safety

- Always use Eloquent or parameterized queries — never raw `DB::raw()` with user input
- Use Spatie QueryBuilder with whitelisted filters/sorts/includes for API endpoints

## Scopes

- Define reusable query logic as local scopes: `scopePublished`, `scopeActive`
- Avoid putting query logic in controllers — use model scopes or dedicated query classes
