# Data Model: JSONB Strategy

> **Date**: February 2026
> **Related Issues**: #42, #43

## Why JSONB

The `raw_data` JSONB column on `transactions` is the single biggest enabler of the reconciliation pipeline. The decision was made early (see `docs/PLAN.md`) and has proven correct:

- **Different banks have different column names** — HDFC statements look nothing like ICICI statements. JSONB absorbs them all without per-bank migrations.
- **Invoice fields don't need their own columns** — GST breakup, vendor GSTIN, HSN codes, line items all go into `raw_data`.
- **Reconciliation enrichment** is just a JSONB merge — matched bank transactions get invoice data added to their `raw_data`.
- **Zero migrations for new fields** — when we discover a new field from a new bank format or invoice layout, we just store it.

### What stays as dedicated columns

Fields that need **indexing, querying, or foreign keys** remain as proper columns:

| Column | Why not JSONB |
|--------|---------------|
| `date` | Indexed, used in range queries |
| `imported_file_id` | Foreign key |
| `account_head_id` | Foreign key |
| `mapping_type` | Enum, filtered/grouped frequently |
| `ai_confidence` | Filtered (`< 0.8` for review) |

### What goes into raw_data

Everything else — the flexible, document-type-specific, potentially-changing fields.

## Data Flow Through raw_data

The `raw_data` column evolves as a transaction moves through the pipeline:

### After Bank Statement Parsing

The AI agent extracts the raw row from the bank statement:

```json
{
  "date": "2025-04-01",
  "description": "ACH/TATACAPFINSERLTD/ICIC0000000017230174",
  "debit": 88609.51,
  "credit": null,
  "balance": 1234567.89,
  "reference": "S60287133"
}
```

Some banks may include extra fields:

```json
{
  "date": "2025-04-15",
  "description": "UPI/SWIGGY/408975321",
  "debit": 450.00,
  "upi_id": "swiggy@ybl",
  "transaction_mode": "UPI"
}
```

The JSONB column absorbs whatever the bank provides. No migration needed.

### After Invoice Parsing (Separate Record)

Invoices create their own transaction records with `statement_type = invoice`:

```json
{
  "vendor_name": "Assetpro Solution Pvt Ltd",
  "vendor_gstin": "29AAQCA1895C1ZD",
  "invoice_number": "ASPL/2439",
  "invoice_date": "2025-03-25",
  "due_date": "2025-04-25",
  "place_of_supply": "Karnataka",
  "line_items": [
    {
      "description": "Office Assistant and Housekeeping charges",
      "hsn_sac": "998519",
      "quantity": 1,
      "rate": 27500.00,
      "amount": 27500.00
    }
  ],
  "base_amount": 27500.00,
  "cgst_rate": 9,
  "cgst_amount": 2475.00,
  "sgst_rate": 9,
  "sgst_amount": 2475.00,
  "igst_rate": null,
  "igst_amount": null,
  "tds_rate": 2,
  "tds_amount": 550.00,
  "total_amount": 31900.00
}
```

### After Reconciliation (Bank Transaction Enriched)

When the reconciliation engine matches a bank entry to an invoice, the bank transaction's `raw_data` is enriched with invoice details:

```json
{
  "date": "2025-04-01",
  "description": "ACH/TATACAPFINSERLTD/ICIC0000000017230174",
  "debit": 88609.51,
  "reference": "S60287133",
  "reconciled_invoice_id": 123,
  "reconciliation_status": "matched",
  "vendor_name": "Assetpro Solution Pvt Ltd",
  "vendor_gstin": "29AAQCA1895C1ZD",
  "invoice_number": "ASPL/2439",
  "base_amount": 27500.00,
  "cgst_amount": 2475.00,
  "sgst_amount": 2475.00,
  "tds_amount": 550.00,
  "line_items": [
    {"description": "Office Assistant charges", "hsn_sac": "998519", "amount": 27500.00}
  ]
}
```

The Tally export reads this enriched data and generates the appropriate voucher type (multi-leg Journal if invoice data is present, simple Payment/Receipt if not).

## Encryption

All `raw_data` is encrypted at rest via Laravel's `encrypted:array` cast. This is important because raw_data contains sensitive financial information (amounts, account numbers, vendor details).

```php
protected function casts(): array
{
    return [
        'raw_data' => 'encrypted:array',
    ];
}
```

**Trade-off**: Encrypted JSONB cannot be queried via PostgreSQL's JSON operators (`->`, `->>`, `@>`). All querying must happen in PHP after decryption. For our use case (small team, not millions of rows), this is acceptable. If we ever need JSONB queries at scale, we'd add unencrypted indexed columns for the specific fields.

## GIN Indexes

If we add unencrypted JSONB columns in the future (e.g., for reconciliation status querying at scale), PostgreSQL GIN indexes provide efficient querying:

```sql
CREATE INDEX idx_transactions_raw_data ON transactions USING GIN (raw_data);

-- Then query:
SELECT * FROM transactions WHERE raw_data @> '{"reconciliation_status": "unmatched"}';
```

Not needed now — just noting the option for future scale.
