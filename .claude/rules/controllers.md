---
paths:
  - "app/Http/Controllers/**"
---

# Controller Rules

## Controller Style

- Use **standard resource methods** (`index`, `show`, `store`, `update`, `destroy`) — never `__invoke` single-action controllers
- One controller per resource/concept, with resource methods for each action
- Keep controllers thin — delegate business logic to services

## Validation & Authorization

- **Always** use Form Request classes for validation — never `$request->validate()`
- Return API Resources for JSON responses — never raw Eloquent models
- Authorize via Form Request `authorize()` method or Policy — never `return true` blindly
- Use route model binding with explicit `where` constraints
- Never catch exceptions in controllers — let domain exceptions with `render()` handle it

## Response Envelope

```php
// Single resource — JsonResource auto-wraps in {"data": ...}
return new PostResource($post);

// Created resource (201)
return (new PostResource($post))->response()->setStatusCode(201);

// Deleted (204)
return response()->json(null, 204);
```

## Status Codes (PD-013)

| Code | When | Response |
|------|------|----------|
| **200** | Successful GET, PUT, PATCH | `{"data": ...}` |
| **201** | Successful POST (creation) | `{"data": ...}` via `->setStatusCode(201)` |
| **204** | Successful DELETE | No body: `response()->json(null, 204)` |
| **401** | Missing/invalid auth token | `{"message": "Unauthenticated."}` + `WWW-Authenticate: Bearer` header |
| **403** | Authenticated but not authorized | `{"message": "This action is unauthorized."}` — never `"Forbidden."` |
| **404** | Resource not found | `{"message": "Not found."}` — never expose model class names |
| **409** | Business rule violation | `{"message": "..."}` (state conflicts, insufficient credits) |
| **422** | Validation errors ONLY | `{"message": "...", "errors": {"field": [...]}}` — handled by Form Requests |
| **429** | Rate limit exceeded | `{"message": "Too Many Attempts."}` + `Retry-After` header |
| **500** | Server error | `{"message": "Server error."}` — never leak internals |

## Pagination

- **Always cap `per_page`**: `min($request->integer('per_page', 15), 100)`
- Use `ResourceClass::collection($paginator)` — Laravel auto-formats `data`, `links`, `meta`
- Default page size: 15 items
- Use `simplePaginate()` when total count isn't needed, `cursorPaginate()` for high-volume endpoints

## Eager Loading

- Only load relationships the specific endpoint uses in its response
- List endpoints (index) load fewer relations than detail endpoints (show)
- When `preventLazyLoading` triggers, the fix is NOT always "add to `with()`" — check if the relation is actually needed in the Resource. If not, remove the access instead

## Query Flexibility (Spatie QueryBuilder)

- Use `HasQueryBuilder` trait for filterable/sortable endpoints
- **Whitelist** all filters, sorts, and includes — Spatie returns 400 for invalid params
- Default sort: `-created_at` (newest first)

## Error Handling

- For business rule violations, throw domain exceptions returning 409
- Domain exceptions define `render()` and `report()` (returning `false` for expected errors)
- `shouldRenderJsonWhen()` in `bootstrap/app.php` forces JSON for all `api/*` routes

## Queuing

- Queue heavy operations (emails, webhooks, exports) — never make API consumers wait
