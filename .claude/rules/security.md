---
paths:
  - "app/Http/**"
  - "config/**"
  - "bootstrap/**"
  - "routes/**"
---

# Security Rules

## Project Guardrails

- Never use `DB::raw()` with user input without `?` parameter binding
- Never use wildcard CORS origins (`*`) with `supports_credentials: true` in `config/cors.php`
- Never expose model class names in 404 responses
- Never expose SQL errors or stack traces in responses
- Never log sensitive data (passwords, tokens, PII) in exception reports
- Never expose auto-increment IDs in security-sensitive contexts
- Never return raw Eloquent models — always use API Resources
- Set token expiration in `config/sanctum.php`
- Force JSON responses via `shouldRenderJsonWhen()` in `bootstrap/app.php`
- Authorize via Policies + route model binding scoping (OWASP: Broken Object-Level Auth)
- Use Form Requests with `$fillable` on models (OWASP: Mass Assignment)
- Rate limit auth endpoints (OWASP: Broken Authentication)

## Config Safety

- Never use `env()` outside config files — it returns `null` when config is cached
- Use `Env::getOrFail()` in config files for required environment variables
- Always access config via `config('key')` in application code
