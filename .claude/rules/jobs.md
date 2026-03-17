---
paths:
  - "app/Jobs/**"
---

# Job Rules

## Design Principles

- Jobs MUST be idempotent — safe to retry without side effects
- Set explicit `$tries`, `$timeout`, and `$backoff` on every job
- Use `ShouldBeUnique` for jobs that shouldn't run concurrently (e.g., report generation)
- Use `$deleteWhenMissingModels = true` for jobs that reference Eloquent models

## Queue Assignment

- Assign jobs to named queues matching the infrastructure topology
- Check `docs/infrastructure/queue-architecture.md` for queue names and priorities
- Never use the `default` queue for production jobs — be explicit

## Error Handling

- Use `failed()` method for cleanup on final failure (not retries)
- Log context (model IDs, user IDs) in `failed()` for debugging
- For expected failures (API down, rate limited), throw and let retry handle it
- For permanent failures (invalid data), call `$this->fail()` to stop retries

## Testing

- Test job dispatch with `Queue::fake()` and `Queue::assertPushed()`
- Test job execution separately with real database (not mocked)
- Verify idempotency: run the job twice, assert same outcome
