# Reconciliation Pipeline

> **Date**: February 2026
> **Status**: Approved
> **Issues**: #40, #41, #42, #43, #15

## Overview

The Virtual CFO application processes financial documents through a four-stage pipeline:

```
Upload → Parse → Reconcile → Export
```

**The Problem**: Bank statements show totals (e.g., "Paid Rs 31,900 to Assetpro"). Tally journal entries need the full breakup — base amount, CGST, SGST, TDS, vendor GSTIN, invoice number. That data lives in the vendor invoices, not the bank statement.

**The Solution**: Upload both bank statements and invoices. The system parses everything, matches bank entries against invoices, flags discrepancies, and produces enriched Tally XML.

## Pipeline Stages

### Stage 1: Upload

Users upload documents through Filament with a `statement_type`:
- `bank` — Bank account statement
- `credit_card` — Credit card statement
- `invoice` — Vendor bill / purchase invoice

Supported formats: PDF, CSV, XLSX.

### Stage 2: Parse

The `DocumentProcessor` service routes by file type:

```
DocumentProcessor::process(ImportedFile $file)
├── CSV/XLSX → Programmatic parser (Maatwebsite Excel, no AI)
├── PDF Bank/CC → StatementParser agent
├── PDF Invoice → InvoiceParser agent
```

All parsed data stored in `raw_data` JSONB — no migrations for new fields.

See: [AI Agent Design](ai-agent-design.md) | [Data Model: JSONB Strategy](data-model-jsonb.md)

### Stage 3: Reconcile

The `ReconciliationService` matches bank transactions against invoices:

1. **Amount match** — exact or within rounding tolerance
2. **Date proximity** — payment within configurable window after invoice date
3. **Party name fuzzy match** — AI fallback when bank narration doesn't match invoice vendor

Match types: 1:1 (most common), 1:N (bulk payment), N:1 (installments).

**Flagged items** (action items for accounts team):
- Bank entry without invoice — subscriptions, bank charges, or missing invoice
- Invoice without bank entry — unpaid vendor bill
- Amount mismatch — TDS deduction, rounding, partial payment

When matched, bank transaction's `raw_data` is enriched with invoice details (GST breakup, vendor GSTIN, line items).

### Stage 4: Export

`TallyExportService` generates Tally-compatible XML:
- **Simple Payment/Receipt vouchers** — bank transactions without invoice (2-leg)
- **Full Journal vouchers** — reconciled transactions with GST breakup (multi-leg)

See: [Tally XML Format](tally-xml-format.md)

## Dependency Graph

```
#40 Company Config ─────────────────────────┐
     (GSTIN, state, name)                    │
                                             ▼
#41 DocumentProcessor ──────────────► #43 Reconciliation ──► #15 Tally Export
     (CSV/XLSX + PDF routing)          (match + enrich)       (XML generation)
                                             ▲
#42 InvoiceParser Agent ────────────────────┘
     (PDF invoice → structured data)
```

- **#40 and #41** — parallel (no mutual dependency)
- **#42** — parallel agent development, integrates with #41
- **#43** — convergence point, needs all three above
- **#15** — final output, reads enriched data from #43

## Detailed Documentation

| Document | Contents |
|----------|----------|
| [AI Agent Design](ai-agent-design.md) | Why focused agents behind one service, SDK patterns, testing |
| [Data Model: JSONB Strategy](data-model-jsonb.md) | Why JSONB, data flow through raw_data, encryption trade-offs |
| [Tally XML Format](tally-xml-format.md) | XML structure, voucher types, field reference, examples from reference file |

## Open Questions

1. **Multi-company support** — Currently single-tenant via config. If Zysk adds clients, need a `companies` table.
2. **Tally ledger name sync** — Ledger names must exactly match Tally. Import from Tally or maintain a mapping?
3. **Credit card without invoices** — CC charges (subscriptions, SaaS) often lack invoices. Generate simple Payment vouchers without reconciliation.
4. **Partial payment handling** — How to represent one invoice paid across multiple bank entries in Tally.
