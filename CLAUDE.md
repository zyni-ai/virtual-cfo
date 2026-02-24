# Virtual CFO - Zysk Technologies

## Project Overview
Virtual CFO application for automating bank/credit card statement processing, account head mapping, and Tally XML export. Built for the accounts team at Zysk Technologies.

## Tech Stack
- PHP 8.4 + Laravel 12
- Filament v5 (admin panel)
- PostgreSQL (single database, JSONB for flexible schemas)
- Laravel AI SDK (`laravel/ai`) with Mistral as primary LLM provider
- Laravel Boost + Filament Blueprint

## Architecture Decisions

### Database: PostgreSQL (not MongoDB)
Originally considered MongoDB for schema flexibility (different banks have different column names). Chose PostgreSQL with JSONB columns instead because:
- Filament does not officially support MongoDB — causes "database engine does not support inserting while ignoring errors"
- JSONB provides identical schema flexibility with `raw_data` column
- Single database eliminates hybrid complexity
- Full ACID transactions, GIN indexes on JSONB, relational integrity

### LLM: Laravel AI SDK (not Prism PHP)
Chose `laravel/ai` over `prism-php/prism` because:
- First-party Laravel package — guaranteed long-term support
- Native agent framework (`php artisan make:agent`)
- Built-in file attachments, queue support, structured output
- Built-in testing with `Agent::fake()` and assertions
- Prism PHP is community-maintained with less integrated features

### PDF Parsing: LLM-powered (not regex/pdfparser)
Using Mistral LLM to parse bank statements instead of smalot/pdfparser because:
- Bank statements have wildly different layouts per bank
- Regex-based parsing requires per-bank parser maintenance
- LLM handles any format — detects columns, extracts structured data
- Works for both text-based and scanned PDFs
- Cost: ~$2/1000 pages via Mistral

### Head Matching: Hybrid (rules + LLM)
Two-pass approach:
1. Rule-based matching (fast, cheap, deterministic) for known patterns
2. LLM-based matching for ambiguous transactions with confidence scores
3. Manual review for anything below confidence threshold

### Encryption
All sensitive financial data encrypted at rest using Laravel's built-in encryption (AES-256-CBC via APP_KEY). Encrypted fields: account_number, description, debit, credit, balance, raw_data.

### File Storage
PDFs stored in `storage/app/private/statements/` — never publicly accessible. Served only through authenticated Filament routes.

## Key Patterns

### Enums
- `ImportStatus`: pending, processing, completed, failed
- `MappingType`: unmapped, auto, manual, ai
- `MatchType`: contains, exact, regex
- `StatementType`: bank, credit_card

### AI Agents
- `StatementParser` — PDF → structured transaction data
- `HeadMatcher` — transaction descriptions → account head suggestions with confidence

### Background Jobs
- `ProcessImportedFile` — parses PDF via StatementParser agent, creates transactions
- `MatchTransactionHeads` — runs rule-based + AI matching on unmapped transactions

## Development Notes
- Very few users (small accounts team)
- Stage-based development — start with import/parse/map/export pipeline
- Tally XML reference file to be provided later
- OCR support via Mistral handles scanned PDFs automatically
