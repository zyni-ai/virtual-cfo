# Exception Handling

Audit of every exception thrown or caught in the application, plus the current handling strategy and gaps.

---

## Current State

- **No custom exception classes** — `app/Exceptions/` does not exist.
- **No global handler config** — `bootstrap/app.php` has an empty `withExceptions()` callback.
- All handling is inline (try/catch at the call site).

---

## Exceptions Thrown

### `\RuntimeException`

| Location | Trigger |
|----------|---------|
| `DocumentProcessor.php:55` | `match` expression hit default arm — unsupported `FileFormat` enum value |
| `DocumentProcessor.php:69` | File extension not in `[pdf, csv, xlsx]` |
| `DocumentProcessor.php:438` | `StatementParser` AI response has no valid `transactions` array |
| `DocumentProcessor.php:640` | `InvoiceParser` AI response missing `vendor_name` or `invoice_number` |
| `PdfDecryptionService.php:58` | `qpdf` process exited with a non-zero, non-recoverable code — includes the error output in the message |
| `ZohoInvoiceService.php:100` | Zoho OAuth token refresh returned a non-2xx response |
| `ZohoInvoiceService.php:140` | Zoho invoice list fetch returned a non-2xx response |

### `\InvalidArgumentException`

| Location | Trigger |
|----------|---------|
| `ReconciliationService.php:499` | `confirmMatch()` called on a match whose status is not `Suggested` |
| `ReconciliationService.php:531` | `rejectMatch()` called on a match whose status is not `Suggested` |

### `NotFoundHttpException`

| Location | Trigger |
|----------|---------|
| `ImportedFileDownloadController.php:22` | The file's storage path does not exist on disk when a user requests a download |

### `Halt` *(Filament-specific)*

| Location | Trigger |
|----------|---------|
| `ViewImportedFile.php:89` | A duplicate mapping rule was detected; user is notified and the Filament action is aborted |
| `ListTransactions.php:92` | Same as above, from the transaction list page |

---

## Exceptions Caught

### Jobs (log → rethrow pattern)

Jobs log the error with context, then rethrow so Laravel's queue driver marks the job as failed, triggers retries, and (on final failure) calls the job's `failed()` handler.

| Job | Exception caught | Behavior |
|-----|-----------------|----------|
| `ProcessImportedFile.php:64` | `\Throwable` | Logs error → sets `ImportedFile.status = Failed` with sanitized message → rethrows |
| `ProcessImportedFile.php:150` | `\Throwable` | Logs warning (non-fatal duplicate detection failure) → swallows |
| `ReconcileImportedFiles.php:66` | `\Throwable` | Logs error → rethrows |
| `MatchTransactionHeads.php:63` | `\Throwable` | Logs error → rethrows |

### AI Middleware

| File | Exception caught | Behavior |
|------|-----------------|----------|
| `AuditLlmCalls.php:25` | `\Throwable` | Logs call as `error` status with message → rethrows (does not suppress AI failures) |

### Console Commands

| File | Exception caught | Behavior |
|------|-----------------|----------|
| `SyncZohoInvoices.php:46` | `\Throwable` | Logs error with `company_id`/`connector_id` context → increments error counter → continues to next company (no rethrow) |

### Filament Pages

| File | Exception caught | Behavior |
|------|-----------------|----------|
| `ViewImportedFile.php:82` | `UniqueConstraintViolationException` | Shows Filament danger notification → throws `Halt` to abort the action |
| `ListTransactions.php:85` | `UniqueConstraintViolationException` | Same as above |
| `EditCompanySettings.php:220` | `\Throwable` | Shows Filament danger notification with `"Sync failed: {message}"` → swallows (no rethrow) |

### HTTP Controllers

| File | Exception caught | Behavior |
|------|-----------------|----------|
| `HealthCheckController.php:33` | `\Throwable` | DB ping failed → returns `'failed'` string (health check always resolves) |
| `HealthCheckController.php:55` | `\Throwable` | Storage write/delete failed → returns `'failed'` string |
| `HealthCheckController.php:68` | `\Throwable` | Stale job query failed → returns `'failed'` string |
| `ImportedFileDownloadController.php:22` | *(throws, not catches)* | — |

### Inbound Email

| File | Exception caught | Behavior |
|------|-----------------|----------|
| `InboundEmailController.php:309` | `UniqueConstraintViolationException` | Duplicate file hash detected → returns `null` silently (idempotent ingest) |

### Services

| File | Exception caught | Behavior |
|------|-----------------|----------|
| `DisplayNameGenerator.php:39` | `\Exception` | `Carbon::parse()` failed on statement period → returns the raw period string as fallback |
| `DocumentProcessor.php:183` | `\Throwable` | Excel serial date conversion failed → returns `null` (row will be skipped) |
| `DocumentProcessor.php:190` | `\Throwable` | `Carbon::parse()` on date string failed → returns `null` (row will be skipped) |
| `DocumentProcessor.php:290` | `\RuntimeException` | Password attempt failed during PDF decryption loop → continues to next password |
| `DocumentProcessor.php:551` | `\Exception` | `Carbon::createFromFormat('d-m-Y')` failed → falls through to generic `Carbon::parse()` |
| `DocumentProcessor.php:559` | `\Exception` | `Carbon::createFromFormat('d/m/Y')` failed → falls through to generic `Carbon::parse()` |
| `DocumentProcessor.php:580` | `\Throwable` | `Carbon::parse()` on statement period failed → falls through to transaction date fallback |

---

## Gaps & Recommendations

### 1. No global exception renderer
`bootstrap/app.php` has an empty `withExceptions()`. Unhandled exceptions surface as Laravel's default HTML error pages (or JSON if the request expects it). Consider adding:
- A rendered response for `NotFoundHttpException` with a user-friendly message
- A rendered response for `\InvalidArgumentException` to return HTTP 422 from API endpoints

### 2. `ZohoInvoiceService` exceptions are unguarded at the call site
Both `RuntimeException`s from the Zoho service propagate up to `SyncZohoInvoices` where they are caught and logged. That is fine, but if `ZohoInvoiceService` is ever called from a non-command context (e.g., a Filament action), the exception will be unhandled.

### 3. `ReconciliationService` `InvalidArgumentException` reaches the Filament layer
`confirmMatch()` and `rejectMatch()` throw `InvalidArgumentException` for invalid state transitions. These are not caught anywhere in the Filament pages that call them — the user would see a generic 500 error page instead of a meaningful notification.

### 4. `UniqueConstraintViolationException` handling is duplicated
Two Filament pages each have an identical try/catch block for duplicate rule detection. A shared action trait or a dedicated service method would eliminate the duplication and make future changes in one place.

### 5. `ProcessImportedFile` sanitises the error message before storing it
The job writes a truncated version of the exception message to `ImportedFile.error_message`. Ensure the sanitisation strips any sensitive data (file paths, API keys in query strings) before persisting.
