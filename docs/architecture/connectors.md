# Connectors Architecture

> **Date**: February 2026
> **Related Issues**: #58 (core infra), #59 (email inbound), #60 (Zoho Invoice)

## Design Decision: Pluggable Connectors Behind Existing Pipeline

Invoice and statement data can arrive from multiple sources. Rather than building separate processing pipelines per source, all connectors are **thin entry points** that create an `ImportedFile` record and dispatch the existing `ProcessImportedFile` job. The entire parsing, matching, and reconciliation pipeline is reused.

```
┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐
│  Manual Upload   │  │  Email Inbound   │  │  Zoho Invoice    │
│  (Filament UI)   │  │  (Mailgun hook)  │  │  (OAuth + cron)  │
└────────┬─────────┘  └────────┬─────────┘  └────────┬─────────┘
         │                     │                      │
         ▼                     ▼                      ▼
   ┌─────────────────────────────────────────────────────────┐
   │              ImportedFile (unified model)                │
   │   source: manual_upload | email | zoho | api            │
   │   source_metadata: {connector-specific IDs for dedup}   │
   └──────────────────────────┬──────────────────────────────┘
                              │
                              ▼
                  ProcessImportedFile job          ← existing
                              │
                              ▼
                  DocumentProcessor::process()     ← existing
                              │
                        ┌─────┴──────┐
                        ▼            ▼
                  CSV/XLSX       PDF → OCR → Agent  ← existing
                        │            │
                        ▼            ▼
                  Create Transactions               ← existing
                              │
                              ▼
                  MatchTransactionHeads job          ← existing
```

### Why This Design

1. **No duplicate logic** — Parsing, head matching, reconciliation are identical regardless of source
2. **Easy to add connectors** — New source = new entry point, same pipeline
3. **Unified UI** — All imported files visible in one Filament table with source filter
4. **Deduplication** — `source_metadata` stores connector-specific IDs (Message-ID for email, zoho_invoice_id for Zoho) to prevent re-processing

---

## Source Tracking

### ImportSource Enum

```php
enum ImportSource: string implements HasLabel {
    case ManualUpload = 'manual_upload';   // Filament file upload (existing)
    case Email = 'email';                  // Inbound email via Mailgun
    case Zoho = 'zoho';                    // Zoho Invoice API sync
    case Api = 'api';                      // Future: direct API upload
}
```

### source_metadata (encrypted JSONB)

Each connector stores its own identifiers for traceability and deduplication:

| Source | Metadata Fields | Purpose |
|--------|----------------|---------|
| `manual_upload` | `null` | N/A — user uploaded directly |
| `email` | `{message_id, from, subject, received_at}` | Email dedup via Message-ID, audit trail |
| `zoho` | `{zoho_invoice_id, zoho_org_id, synced_at}` | Invoice dedup, Zoho reference |
| `api` | `{api_client_id, request_id}` | API request tracing |

---

## Connector 1: Email Inbound (Mailgun)

### Flow

```
Accounts team receives invoice email from vendor
    → Forwards to zysk-a7f3bc@inbox.virtualcfo.zysk.tech
    → Mailgun receives (catch-all route on subdomain)
    → Mailgun POSTs webhook to /api/v1/webhooks/inbound-email
    → App verifies Mailgun HMAC signature
    → Resolves tenant: Company::where('inbox_address', $recipient)
    → Extracts PDF/image attachments (skips non-supported types)
    → Per attachment: creates ImportedFile + dispatches ProcessImportedFile
    → Existing pipeline: OCR → InvoiceParser → HeadMatcher
```

### Per-Tenant Email Addresses

Format: `{company-slug}-{6-char-hash}@inbox.virtualcfo.zysk.tech`

- The slug is human-readable (users can identify which company)
- The hash (derived from company ID + APP_KEY) prevents address guessing
- Stored on `companies.inbox_address`
- Regeneratable if compromised (invalidates old address)

Example: `zysk-a7f3bc@inbox.virtualcfo.zysk.tech`

### Mailgun Setup

1. **Domain**: `inbox.virtualcfo.zysk.tech` (MX records pointing to Mailgun)
2. **Route**: Catch-all `.*@inbox.virtualcfo.zysk.tech` → forward to webhook URL
3. **Webhook**: `POST https://app.virtualcfo.zysk.tech/api/v1/webhooks/inbound-email`

### Security

| Measure | Implementation |
|---------|---------------|
| Webhook signature | HMAC-SHA256 verification of Mailgun `timestamp + token` |
| Stale webhook rejection | Reject if `timestamp` is > 5 minutes old |
| Tenant resolution | Only known `inbox_address` values resolve; unknown addresses return 404 |
| CSRF exemption | Webhook route excluded from CSRF middleware |
| Attachment filtering | Only `application/pdf`, `image/png`, `image/jpeg` accepted |
| Deduplication | Email Message-ID stored in `source_metadata`; duplicates rejected |

### What Gets Logged (Audit)

| Field | Logged? |
|-------|---------|
| From address | Yes |
| To address | Yes |
| Subject line | Yes |
| Message-ID | Yes |
| Attachment filenames | Yes |
| **Email body content** | **Never** |

---

## Connector 2: Zoho Invoice (OAuth + Scheduled Sync)

### Flow

```
Hourly cron: php artisan connectors:sync-zoho
    → For each company with active Zoho connector:
        → Refresh OAuth token if within 5 min of expiry
        → GET /api/v3/invoices?last_modified_time > last_synced_at
        → For each new invoice:
            → Skip if zoho_invoice_id already in source_metadata (dedup)
            → Download PDF: GET /api/v3/invoices/{id}?accept=pdf
            → Store in storage/app/private/statements/
            → Create ImportedFile (source: zoho)
            → Dispatch ProcessImportedFile
        → Update connector.last_synced_at
```

### OAuth 2.0 Flow

1. User clicks "Connect Zoho" in Filament
2. Redirect to `https://accounts.zoho.in/oauth/v2/auth` with scopes
3. Zoho redirects back to `/connectors/zoho/callback` with auth code
4. App exchanges code for access + refresh tokens
5. Tokens stored encrypted in `connectors` table
6. Access token auto-refreshed (1-hour expiry) using refresh token

### Indian Zoho Endpoints

Zoho India uses `.in` TLD (not `.com`):

| Service | URL |
|---------|-----|
| OAuth | `https://accounts.zoho.in/oauth/v2/` |
| API | `https://www.zohoapis.in/api/v3/` |

### Connectors Table

Shared table for all external integrations (Zoho now, future: Tally, QuickBooks):

```
connectors
├── id
├── company_id (FK)
├── provider (enum: zoho, ...)
├── access_token (encrypted)
├── refresh_token (encrypted)
├── token_expires_at
├── settings (encrypted JSONB — org_id, scopes, etc.)
├── last_synced_at
├── is_active (boolean)
├── timestamps
└── soft_deletes
    UNIQUE(company_id, provider) WHERE deleted_at IS NULL
```

---

## Adding a New Connector (Future)

To add a new source (e.g., Tally, QuickBooks, SFTP):

1. Add value to `ImportSource` enum
2. Create service class: `App\Services\Connectors\{Name}Service`
3. The service must:
   - Fetch/receive the file
   - Store it in `storage/app/private/statements/`
   - Create `ImportedFile` with correct `source` and `source_metadata`
   - Dispatch `ProcessImportedFile`
4. Create the trigger (webhook controller, scheduled command, or Filament action)
5. If OAuth: use the `connectors` table for token storage
6. Add deduplication logic using `source_metadata`

The processing pipeline (OCR, parsing, matching) requires zero changes.

---

## Data Privacy Considerations

See [Data Privacy Strategy](data-privacy-strategy.md) for full details.

- Email bodies are **never stored or logged** — only metadata (from, subject, message-id)
- Zoho API tokens are **encrypted at rest** in the `connectors` table
- All downloaded PDFs go to **private storage** (never public)
- `source_metadata` is **encrypted** (contains message-ids, zoho IDs)
- Inbound email webhook signature verification prevents injection
