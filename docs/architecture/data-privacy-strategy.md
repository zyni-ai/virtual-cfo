# Data Privacy Strategy

> **Date**: February 2026
> **Related Issues**: #54 (pseudonymization + audit), #55 (PostgreSQL SSL + RLS)

## Threat Model

This application processes **sensitive financial documents** — bank statements, credit card statements, and vendor invoices — for multiple companies. The data contains:

- Bank account numbers, IFSC codes
- Transaction descriptions with party names
- Debit/credit amounts and running balances
- Vendor GSTINs, PAN numbers
- Invoice amounts and line items

### Attack Surface

| Vector | Risk | Current Mitigation |
|--------|------|--------------------|
| Data at rest (database) | Stolen database dump exposes financials | AES-256-CBC encryption on all sensitive fields |
| Data in transit (network) | MITM intercepts DB or API traffic | HTTPS for APIs; PostgreSQL `sslmode` (see [improvement needed](#postgresql-hardening)) |
| Data sent to external LLMs | Provider stores/leaks/trains on financial data | **Partially mitigated** — see [LLM Data Exposure](#llm-data-exposure) |
| Cross-tenant data leakage | Company A sees Company B's data | Filament v5 auto-scoping (see [improvement needed](#row-level-security)) |
| Audit trail gaps | No record of what data was processed | Activity logging on models (see [improvement needed](#llm-audit-logging)) |

---

## LLM Data Exposure

### What Goes to External LLMs

| Agent | Data Sent | Sensitivity | Can Pseudonymize? |
|-------|-----------|-------------|-------------------|
| **OcrService** → Mistral OCR | Raw PDF (base64 encoded) | **Critical** — entire document | No — the PDF IS the input |
| **StatementParser** → LLM | OCR-extracted markdown text | **Critical** — bank name, account number, all transactions | No — agent's job is to extract these |
| **InvoiceParser** → LLM | OCR-extracted markdown text | **Critical** — vendor GSTIN, amounts, line items | No — agent's job is to extract these |
| **HeadMatcher** → LLM | Transaction descriptions + amounts | **High** — party names, amounts | **Yes** — matching uses keywords, not identities |

### The Fundamental Tension

For OCR and parsing agents, there is a chicken-and-egg problem: we need the LLM to extract account numbers and bank names, but we want to hide those from the LLM. Stripping this data defeats the purpose.

For the HeadMatcher, pseudonymization works cleanly because transaction categorization depends on **type keywords** (NEFT, RTGS, UPI, SALARY, EMI), not party identities.

---

## Strategy: Hybrid Approach

### Tier 1: Pseudonymization (HeadMatcher)

Build a reversible tokenization service that masks PII before sending to the LLM:

```
"NEFT/RTGS from ABC Corp Ltd"       → "NEFT/RTGS from [ENTITY_1]"
"UPI/987654@okicici A/C 12345678"   → "UPI/[REF_1]@[UPI_1] A/C [ACCT_1]"
"EMI Home Loan HDFC 0012345678"     → "EMI Home Loan [BANK_1] [ACCT_1]"
"Salary from Zysk Technologies"     → "Salary from [ENTITY_1]"
```

**Why this works:** The LLM still correctly maps these to "Vendor Payment", "UPI Payments", "Loan EMI", "Salary" — because classification depends on the transaction type keywords, not the party name.

**Indian financial PII patterns to detect:**

| Pattern | Regex | Example |
|---------|-------|---------|
| Account numbers | `\b\d{10,18}\b` | `50100123456789` |
| PAN | `[A-Z]{5}[0-9]{4}[A-Z]` | `ABCDE1234F` |
| GSTIN | `\d{2}[A-Z]{5}\d{4}[A-Z]\d[Z][A-Z\d]` | `29ABCDE1234F1Z5` |
| UPI handles | `[\w.]+@[\w]+` | `name@okicici` |
| Phone numbers | `\b[6-9]\d{9}\b` | `9876543210` |
| Email addresses | Standard email regex | `user@example.com` |
| IFSC codes | `[A-Z]{4}0[A-Z0-9]{6}` | `HDFC0001234` |

**Implementation:** `App\Services\DataPrivacy\Pseudonymizer` — integrated into `HeadMatcherService` as a pre/post processing step.

### Tier 2: Provider-Level Protections (OCR + Parsing)

Since OCR and parsing agents **must** see the data:

1. **Zero-data-retention APIs** — Choose providers that guarantee no storage or training on API data
2. **Data Processing Agreements (DPAs)** — Sign legal agreements with:
   - Mistral (for OCR endpoint)
   - OpenRouter (for text generation — note: data passes through OpenRouter AND downstream provider)
3. **Minimal data principle** — Only send what's necessary in prompts; don't include extra context
4. **Provider selection criteria:**
   - Must offer zero-retention API tier
   - Must be willing to sign DPA
   - Prefer EU-based providers for GDPR alignment (Mistral is French)
   - Check OpenRouter's downstream provider data handling

### Tier 3: LLM Audit Logging

Create an agent middleware (`App\Ai\Middleware\AuditLlmCalls`) that records:

| Field | Logged | Example |
|-------|--------|---------|
| Agent name | Yes | `StatementParser` |
| Model used | Yes | `mistralai/mistral-large-latest` |
| Provider | Yes | `openrouter` |
| File ID / Transaction IDs | Yes | `file_id: 42, tx_ids: [101, 102, ...]` |
| Timestamp | Yes | `2026-02-26T10:30:00Z` |
| Token usage | Yes | `input: 2400, output: 1800` |
| Response status | Yes | `success` / `failed` |
| **Prompt content** | **Never** | — |
| **Response content** | **Never** | — |

This provides a compliance audit trail without creating a second copy of sensitive data in logs.

---

## Database-Level Security (Current State)

### Encryption at Rest

All sensitive financial fields are encrypted using Laravel's `encrypted` cast (AES-256-CBC via `APP_KEY`):

| Model | Encrypted Fields |
|-------|-----------------|
| `Transaction` | `description`, `debit`, `credit`, `balance`, `raw_data` |
| `ImportedFile` | `account_number` |
| `BankAccount` | `account_number` |

**Trade-off:** Encrypted columns cannot be queried via SQL. All filtering/searching happens in PHP after decryption. Acceptable at current scale (small accounts team, not millions of rows).

### Activity Logging

Spatie Activity Log tracks all changes to financial models. **Encrypted fields are deliberately excluded** from logs — only metadata (IDs, statuses, timestamps) is recorded.

Retention: 365 days (`config/activitylog.php`).

### File Storage

PDFs stored in `storage/app/private/statements/` — never publicly accessible. Served only through authenticated routes with policy authorization checks.

### Multi-Tenant Isolation

All financial tables have `company_id` foreign keys. Filament v5's `->tenant(Company::class)` auto-scopes queries and auto-associates records on creation.

---

## Improvements Needed

### PostgreSQL Hardening

**SSL mode** (`config/database.php`):
- Current: `sslmode: 'prefer'` — falls back to unencrypted
- Target: `sslmode: 'require'` in production (configurable via `DB_SSLMODE` env var)

**Row-Level Security** (defense-in-depth for multi-tenancy):
- Enable RLS on all tenant-scoped tables
- Application sets `app.current_company_id` session variable per request
- Database enforces `company_id` filtering even for direct SQL access
- See issue #55

### LLM Audit Logging

No current record of what data was sent to external LLMs. The `AuditLlmCalls` middleware (issue #54) addresses this.

---

## Future Escalation Path

As the customer base grows or compliance requirements tighten:

| Phase | Change | Trigger |
|-------|--------|---------|
| **Phase 1** (current) | Pseudonymize HeadMatcher + provider DPAs + audit logging | Now |
| **Phase 2** | Replace Mistral OCR with self-hosted PaddleOCR/Tesseract | Enterprise clients or RBI compliance |
| **Phase 3** | Self-hosted parsing LLM (Mistral/Llama on local GPU) | Data must never leave infrastructure |
| **Phase 4** | On-premise deployment option | Client demands on-prem |

**Phase 2 trade-off:** Local OCR quality is significantly worse than Mistral OCR for scanned/rotated documents. PaddleOCR handles Indian scripts (Hindi, Tamil, etc.) better than Tesseract but still lags behind cloud OCR.

**Phase 3 trade-off:** Requires GPU infrastructure (NVIDIA A100 or similar). Running Mistral Large locally needs ~80GB VRAM. Smaller models (Mistral 7B, Llama 8B) may work for parsing but with lower accuracy.

---

## Decision Log

| Date | Decision | Rationale |
|------|----------|-----------|
| Feb 2026 | Encrypt all sensitive fields at rest (AES-256-CBC) | Financial data must be unreadable in DB dumps |
| Feb 2026 | Exclude encrypted fields from activity logs | Audit trail should not duplicate sensitive data |
| Feb 2026 | Private file storage with policy-guarded downloads | PDFs must never be publicly accessible |
| Feb 2026 | Hybrid LLM privacy: pseudonymize HeadMatcher, DPAs for rest | Only practical approach — OCR/parsing cannot be pseudonymized |
| Feb 2026 | Defer self-hosted OCR/LLM to Phase 2/3 | Small team, few customers — provider DPAs are sufficient for now |
