# Virtual CFO - Project Setup Plan

## Context
Zysk Technologies needs a Virtual CFO application to automate the tedious process of importing bank/credit card statements (PDFs), mapping transactions to Tally accounting heads, and exporting Tally-compatible XML. Currently this is done manually by accounts executives. Stage 1 focuses on the import-parse-map-export pipeline.

## Tech Stack
- **PHP 8.4 + Laravel 12**
- **Filament v5** (latest stable ~5.2)
- **PostgreSQL** (single database, JSONB for schema flexibility)
- **Laravel AI SDK** (`laravel/ai`) — first-party Laravel package for LLM integration
- **Mistral** (primary LLM provider) — for PDF parsing and head matching
- **Laravel built-in encryption** (AES-256-CBC) for sensitive data at rest
- **Laravel Boost + Filament Blueprint** for AI-assisted resource generation

### Key Architecture Decisions

**PostgreSQL + JSONB**: Single database, flexible schema via `raw_data JSONB` column for different bank formats. Full Filament compatibility, ACID transactions, GIN indexes on JSONB.

**Laravel AI SDK over Prism PHP**: First-party Laravel package with native agent framework, file attachments, queue support, structured output, and built-in testing. Agents are created via `php artisan make:agent`. Supports Mistral, OpenAI, Anthropic, Gemini, and more — easy to swap providers.

**LLM-powered PDF parsing**: Attach PDF directly to a Mistral agent, ask it to return structured JSON (date, description, debit, credit, balance). Works for any bank format without per-bank parsers. The LLM handles layout detection, column mapping, and data extraction.

**Hybrid head matching (rules + LLM)**:
1. Rule-based first pass — fast, cheap, deterministic for known patterns
2. LLM second pass — handles ambiguous transactions with confidence scores
3. Manual review — anything below confidence threshold

---

## Step 1: Laravel Project Scaffolding

```bash
cd D:\Code\virtual-cfo
composer create-project laravel/laravel . --prefer-dist
```

Install packages:
```bash
composer require filament/filament:"~5.0" -W
composer require laravel/ai
composer require laravel/boost --dev
```

Run installers:
```bash
php artisan filament:install --panels
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
php artisan migrate   # includes AI SDK tables
php artisan boost:install   # select Filament
```

---

## Step 2: Environment Configuration

**`.env`**:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=virtual_cfo
DB_USERNAME=postgres
DB_PASSWORD=<password>

MISTRAL_API_KEY=<key>
```

---

## Step 3: Enums (`app/Enums/`)

- **ImportStatus**: pending, processing, completed, failed
- **MappingType**: unmapped, auto, manual, ai
- **MatchType**: contains, exact, regex
- **StatementType**: bank, credit_card

---

## Step 4: Migrations

### `imported_files`
```
id, bank_name, account_number (text, encrypted), statement_type (enum),
file_path, original_filename, file_hash (sha256),
status (enum: pending/processing/completed/failed),
total_rows, mapped_rows, error_message,
uploaded_by (FK users), processed_at, timestamps
```

### `transactions`
```
id, imported_file_id (FK), date, description (text, encrypted),
reference_number, debit (decimal, encrypted), credit (decimal, encrypted),
balance (decimal, encrypted), account_head_id (FK nullable),
mapping_type (enum: unmapped/auto/manual/ai),
ai_confidence (float nullable), raw_data (JSONB, encrypted),
bank_format (string), timestamps

Indexes: imported_file_id, account_head_id, mapping_type, date
GIN index on raw_data
```

### `account_heads`
```
id, name, parent_id (self-ref FK nullable), tally_guid (nullable),
group_name, is_active (boolean default true), timestamps
Index: name, group_name
```

### `head_mappings`
```
id, pattern (text), match_type (enum: contains/exact/regex),
account_head_id (FK), bank_name (nullable),
created_by (FK users), usage_count (int default 0), timestamps
Index: account_head_id, bank_name
```

---

## Step 5: Models (`app/Models/`)

**ImportedFile**
- Casts: `status` -> ImportStatus, `account_number` -> encrypted, `processed_at` -> datetime
- Relations: `belongsTo(User, 'uploaded_by')`, `hasMany(Transaction)`

**Transaction**
- Casts: `description` -> encrypted, `debit` -> encrypted, `credit` -> encrypted, `balance` -> encrypted, `raw_data` -> encrypted:array, `mapping_type` -> MappingType, `date` -> date
- Relations: `belongsTo(ImportedFile)`, `belongsTo(AccountHead)`
- Scopes: `unmapped()`, `mapped()`, `needsReview()`

**AccountHead**
- Relations: `hasMany(Transaction)`, `hasMany(HeadMapping)`, self-ref `parent()`/`children()`

**HeadMapping**
- Casts: `match_type` -> MatchType
- Relations: `belongsTo(AccountHead)`, `belongsTo(User, 'created_by')`

---

## Step 6: AI Agents (`app/Ai/Agents/`)

### StatementParser Agent

```bash
php artisan make:agent StatementParser --structured
```

**`app/Ai/Agents/StatementParser.php`**:
- Provider: Mistral (configured via `#[Provider(Lab::Mistral)]`)
- Instructions: "You are a financial document parser. Extract all transactions from bank/credit card statements. Return structured data."
- Structured output schema:
  - `bank_name` (string)
  - `account_number` (string, nullable)
  - `statement_period` (string)
  - `transactions` (array of objects: date, description, debit, credit, balance, reference)
- Input: PDF attached via `Document::fromStorage($path)`
- Used in ProcessImportedFile job

### HeadMatcher Agent

```bash
php artisan make:agent HeadMatcher --structured
```

**`app/Ai/Agents/HeadMatcher.php`**:
- Provider: Mistral
- Instructions: "You are an accounting expert. Given transaction descriptions and a chart of accounts, suggest the most appropriate account head for each transaction."
- Structured output schema:
  - `matches` (array of objects: transaction_id, suggested_head_name, confidence 0-1, reasoning)
- Input: batch of unmapped transaction descriptions + chart of accounts as context
- Used in MatchTransactionHeads job (second pass after rule-based matching)

---

## Step 7: Services

### RuleBasedMatcher — `app/Services/HeadMatcher/RuleBasedMatcher.php`
- Loads HeadMapping rules from DB
- Matches transaction descriptions against patterns (contains/exact/regex)
- Returns matches with `mapping_type = auto`

### HeadMatcherService — `app/Services/HeadMatcher/HeadMatcherService.php`
- Orchestrates: rule-based first, AI second, returns results
- Configurable confidence threshold (default 0.8)

### TallyExportService — `app/Services/TallyExport/TallyExportService.php`
- Placeholder for Tally XML generation (format TBD)

---

## Step 8: Background Jobs

**`ProcessImportedFile`** — `app/Jobs/ProcessImportedFile.php`
1. Set status -> processing
2. Attach PDF to StatementParser agent: `Document::fromStorage($importedFile->file_path)`
3. Call `->prompt('Parse this bank statement...')` -> get structured JSON
4. Bulk insert Transaction rows with encrypted fields
5. Update ImportedFile totals, set status -> completed/failed
6. Dispatch MatchTransactionHeads

**`MatchTransactionHeads`** — `app/Jobs/MatchTransactionHeads.php`
1. Run RuleBasedMatcher on unmapped transactions
2. Run HeadMatcher agent on remaining unmapped (batch of descriptions + chart of accounts)
3. Apply AI matches above confidence threshold -> `mapping_type = ai`
4. Update ImportedFile mapped_rows

---

## Step 9: Filament Resources

### ImportedFileResource
- **List**: Status badges, row counts, mapped %, bank name, upload date
- **Create**: File upload (.pdf, multiple), statement type select. Bank name auto-detected by LLM.
- **View**: Import details + embedded transaction table
- **Actions**: Re-process, Delete, View Transactions

### TransactionResource
- **List**: Date, description, debit, credit, balance, assigned head, mapping type badge, AI confidence
- **Filters**: Imported file, mapping status, date range, bank
- **Bulk action**: Assign head to selected rows
- **Row action**: Inline account head select (sets mapping_type = manual)
- **Row action**: "Create Rule" — save as HeadMapping for future auto-matching
- **Header action**: "Export to Tally XML" (placeholder)
- **Header action**: "Run AI Matching" — trigger on unmapped rows

### AccountHeadResource
- **List**: Searchable — name, group, parent, active status
- **Create/Edit**: CRUD with self-referencing parent
- **Action**: "Import from Tally XML" (placeholder)

### HeadMappingResource
- **List**: Pattern, match type, target head, bank, usage count
- **Create/Edit**: Pattern, match type, account head, bank
- **Action**: "Test Rule"

### Dashboard Widgets
- **StatsOverview**: Total files, transactions, mapped %, unmapped count
- **RecentImports**: Last 5 with status

---

## Step 10: Security

1. **Private storage**: PDFs in `storage/app/private/statements/`
2. **Encrypted fields**: account_number, description, debit, credit, balance, raw_data — via Laravel `encrypted` / `encrypted:array` casts
3. **File access**: Only through authenticated Filament routes
4. **Auth**: Filament panel login
5. **Duplicate detection**: SHA-256 hash on upload
6. **API keys**: In `.env`, never committed (`.gitignore`)

---

## Step 11: Implementation Order

1. Create Laravel 12 project + install packages (Filament v5, Laravel AI SDK, Boost)
2. Configure PostgreSQL + Mistral API key in `.env`
3. Publish AI SDK config + run migrations
4. Create Enums
5. Create business table migrations + run them
6. Create Models with casts, relations, scopes
7. Set up Filament panel + create admin user
8. Build StatementParser agent (structured output for PDF -> transactions)
9. Build ImportedFileResource (upload, list, view)
10. Build ProcessImportedFile job (uses StatementParser agent)
11. Build AccountHeadResource (CRUD)
12. Build HeadMappingResource (CRUD + test action)
13. Build RuleBasedMatcher service
14. Build HeadMatcher agent (structured output for description -> head)
15. Build MatchTransactionHeads job (rule-based + AI)
16. Build TransactionResource (list, filter, assign heads, bulk actions)
17. Build Dashboard widgets
18. Build TallyExportService placeholder + export action
19. Security review + initial git commit

---

## Verification
1. `php artisan serve` — Filament admin loads at /admin
2. Create admin user, log in
3. Upload a bank statement PDF -> job dispatches -> Mistral parses it
4. Transactions appear in table with correct data
5. Encrypted fields unreadable in raw DB (`psql` check)
6. Add account heads + mapping rules -> rule-based matching works
7. AI matcher handles remaining rows with confidence scores
8. Manually assign heads -> confirm persistence
9. "Create Rule" from manual assignment works
10. PDFs not accessible via direct URL
11. Export action downloads placeholder XML
