# Queue Strategy

> **Date**: February 2026
> **Status**: Approved
> **Related Issues**: #4

## Design Decision: Database Queue (not Redis + Horizon)

We originally set up Redis with Laravel Horizon for queue processing. After evaluating actual usage patterns, we removed both in favour of PostgreSQL-backed database queues.

## Options Evaluated

| Approach | Pros | Cons |
|----------|------|------|
| **Redis + Horizon** | Fast dispatch, real-time dashboard, auto-scaling workers | Extra infrastructure dependency, overkill for low throughput |
| **Database queue** | Zero extra dependencies, ACID guarantees, SQL-inspectable jobs | Slower dispatch (~10ms vs ~1ms), no built-in dashboard |
| **Sync (no queue)** | Simplest, no worker needed | Blocks HTTP request, poor UX for PDF parsing |

**Decision: Database queue with `php artisan queue:work`.**

## Rationale

### 1. The bottleneck is the AI API, not the queue

Each job calls Mistral's API to parse PDFs — this takes 5-30 seconds per document. Whether Redis dispatches the job in 1ms or the database in 10ms is irrelevant. The queue driver is never the bottleneck.

### 2. Very few users

The accounts team at Zysk uploads ~10-20 files per day. Database queues handle thousands of jobs per hour. We will never hit the throughput ceiling.

### 3. One less infrastructure dependency

Redis needs to be installed, configured, monitored, and kept running. For a small internal tool, this is unnecessary operational burden. PostgreSQL is already running — reuse it.

### 4. Jobs are inspectable via SQL

With database queues, jobs live in the `jobs` and `failed_jobs` tables. You can query them directly:

```sql
SELECT * FROM jobs WHERE queue = 'default' ORDER BY created_at DESC;
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 5;
```

No need for a separate dashboard (Horizon) to inspect queue state.

### 5. ACID guarantees

Database queues are transactional. A job is either in the queue or it isn't — no risk of losing jobs if the queue backend restarts without proper persistence configured (a common Redis pitfall without AOF/RDB).

## When to Reconsider

Revisit this decision if:
- The application scales beyond ~100 files/day
- Real-time job monitoring becomes critical (e.g., SLA tracking)
- Multiple queue workers with priority routing are needed
- The application adds WebSocket-based live updates that need Redis pub/sub

## Running the Queue Worker

Development:
```bash
php artisan queue:work
```

Production:
```bash
# Supervised by systemd, Supervisor, or similar
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

## Configuration

Queue config lives in `config/queue.php`. The default driver is set via `QUEUE_CONNECTION` env var (defaults to `database`).

```php
// config/queue.php
'default' => env('QUEUE_CONNECTION', 'database'),
```
