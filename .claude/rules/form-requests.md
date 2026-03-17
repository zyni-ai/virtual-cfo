---
paths:
  - "app/Http/Requests/**"
---

# Form Request Rules

## Project Conventions

- **Always** use Form Request classes — never inline `$request->validate()` in controllers
- `authorize()` must check permissions via policies — never `return true` blindly for write operations
- Use **array format** for rules (`['required', 'string']`), not pipe-delimited (`'required|string'`)
- Use `prepareForValidation()` for input normalization (trim, slug, lowercase) — not in controllers
- Add `bodyParameters()` for Scribe API documentation on complex inputs
- Validate `per_page` with `max:100` when accepting pagination input

## Naming

- `Store*Request` — POST/create operations
- `Update*Request` — PUT/PATCH operations
- File location: `app/Http/Requests/Api/V1/<OperationRequest>.php`

## Don't

- Don't use inline `$request->validate()` in controllers
- Don't duplicate validation logic across requests — extract to custom Rule objects
- Don't use pipe-delimited rules — use array format
