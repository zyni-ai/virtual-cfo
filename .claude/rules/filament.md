---
paths:
  - "app/Filament/**"
  - "resources/views/vendor/filament-panels/**"
---

# Filament Rules

## Conventions

- Use `/mcp__laravel-boost__filament/filament` to load Filament v5 guidelines before building resources
- Check `docs/guides/filament-customizations.md` for project-specific overrides

## Tenant URLs

- `AdminPanelProvider` uses `slugAttribute: 'slug'` for tenant routing
- Do NOT add `getRouteKeyName()` to the Organization model — it breaks API routes
- In published views, use `$panel->getTenantSlugAttribute()` + `$tenant->getAttributeValue()` for slug-aware URLs

## Published Views

- Published views in `resources/views/vendor/filament-panels/` override vendor defaults
- These do NOT auto-update on Filament upgrades — review after any Filament version bump
- Only publish views when customization is needed — prefer Filament's extension points first

## Testing CRUD Resources

- Every Filament resource test must verify form fields, table records, or visible content
- Don't write `assertOk()`-only tests — assert specific data is rendered
