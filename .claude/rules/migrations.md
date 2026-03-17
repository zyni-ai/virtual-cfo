---
paths:
  - "database/migrations/**"
---

# Migration Rules

## PostgreSQL Standards (non-negotiable)

| Use | Not |
|-----|-----|
| `text()` | `string()` with length / `VARCHAR(n)` |
| `timestampTz()` | `timestamp()` |
| `id()` (Laravel's `BIGINT` identity) | `increments()` / `SERIAL` |
| `jsonb()` + CHECK constraint | `json()` |
| Explicit `->index()` on every FK | Assume auto-indexing |
| Partial unique `WHERE deleted_at IS NULL` | Table-level unique with soft deletes |

## Before creating a migration

1. Use `database-schema` MCP tool to review existing tables
2. Check the relevant ADR in `docs/adr/` for design decisions
3. Check `docs/schema/` for original SQL (source of truth)

## Always include

- Only `up()` method — no `down()` method (trunk-based development, no rollbacks in production)
- Explicit FK indexes: `$table->index('foreign_key_column')`
- Comments on non-obvious columns or constraints
