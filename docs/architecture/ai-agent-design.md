# AI Agent Design

> **Date**: February 2026
> **Related Issues**: #41, #42

## Design Decision: One Service, Focused Agents

We evaluated three approaches for document parsing:

| Approach | Pros | Cons |
|----------|------|------|
| **One agent for everything** | Simple architecture | Prompt bloat, schema conflicts, LLM hallucination risk |
| **Anonymous agents per type** | Maximum flexibility | Hard to test (no `Agent::fake()`), no class-level config |
| **Focused agents behind one service** | Clean prompts, testable, type-safe schemas | Multiple agent classes |

**Decision: Focused agents behind a unified service (option 3).**

### Why Not One Agent?

The core question was: "Parsing a bank statement and parsing an invoice are fundamentally the same job — read a document, extract structured data. Why not one agent?"

It's a fair challenge. At the **service level**, yes — one entry point (`DocumentProcessor`). But at the **agent level**, separate classes win for these reasons:

1. **Prompt quality degrades with scope.** A prompt that says "you might receive a bank statement, or a credit card statement, or a vendor invoice — figure out which and extract accordingly" will perform worse than a focused prompt that says "extract transactions from this bank statement." LLMs do better with clear, narrow instructions.

2. **Output schemas are fundamentally different.** A bank statement yields `{transactions: [{date, description, debit, credit, balance}]}`. An invoice yields `{vendor_name, gstin, invoice_number, line_items: [{description, hsn, rate, cgst, sgst}]}`. These aren't variations of the same shape — they're structurally incompatible. A union schema risks the LLM hallucinating invoice fields when parsing a bank statement (it sees `gstin` in the schema and invents one).

3. **The laravel/ai SDK schema() method is static.** It returns a fixed array per class. There's no mechanism to return different schemas based on constructor args. The SDK *does* support anonymous agents with dynamic schemas, but those lose `Agent::fake()` and `assertPrompted()` testing.

4. **Failure modes differ.** Bank statement with 200 rows — row 150 fails? You want partial results. Invoice parsing fails? Reject the whole thing — partial invoice data is dangerous for accounting.

5. **Model/cost optimization.** Bank statements are fairly structured (tables) — a cheaper model handles them fine. Invoices vary wildly — might need a more capable model. One agent = one model for everything.

### The Architecture

```
DocumentProcessor (service — single entry point for callers)
├── CSV/XLSX → Programmatic parser (Maatwebsite Excel, no AI)
├── PDF Bank/CC Statement → StatementParser agent
├── PDF Invoice → InvoiceParser agent
```

The caller never interacts with individual agents — they call `DocumentProcessor::process($file)`. The agents are **implementation details** inside the service.

### Agent Inventory

| Agent | Purpose | Input | Output Schema |
|-------|---------|-------|---------------|
| `StatementParser` | Bank/CC statement PDFs | PDF attachment | `{bank_name, account_number, transactions[]}` |
| `InvoiceParser` | Vendor invoice PDFs | PDF attachment | `{vendor_name, gstin, invoice_number, line_items[], gst_breakup}` |
| `HeadMatcher` | Transaction classification | Descriptions + chart of accounts | `{matches: [{transaction_id, head_id, confidence}]}` |

### What Doesn't Need AI

- **CSV/XLSX parsing** — Structured data, parsed programmatically via Maatwebsite Excel. No AI cost, no latency, deterministic.
- **Reconciliation matching** — Rule-based (amount + date + party name). AI only as fallback for fuzzy party name matching.
- **Tally XML generation** — Pure serialization from structured data.

## laravel/ai SDK Patterns

### Agent Definition

```php
#[Provider('mistral')]
#[MaxTokens(8192)]
#[Temperature(0.1)]
class StatementParser implements Agent, HasStructuredOutput
{
    use Promptable;

    public function model(): string
    {
        return config('ai.models.parsing', 'mistral-large-latest');
    }

    public function instructions(): string
    {
        return 'You are a financial document parser specializing in bank statements...';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'bank_name' => $schema->string()->required(),
            'transactions' => $schema->array()->items($schema->object([
                'date' => $schema->string()->required(),
                'description' => $schema->string()->required(),
                'debit' => $schema->number(),
                'credit' => $schema->number(),
                'balance' => $schema->number(),
            ]))->required(),
        ];
    }
}
```

### Invocation with File Attachment

```php
$response = (new StatementParser)->prompt(
    'Parse all transactions from this bank statement.',
    attachments: [Document::fromStorage($file->file_path)]
);

$transactions = $response['transactions']; // structured array access
```

### Dynamic Context via Constructor/Fluent Methods

```php
// HeadMatcher accepts chart of accounts as runtime context
$response = (new HeadMatcher)
    ->withChartOfAccounts($chartString)
    ->prompt('Match these transactions to account heads: ...');
```

### Testing

```php
// Fake responses (auto-generates data matching schema)
StatementParser::fake();

// Fake with specific data
StatementParser::fake([json_encode(['bank_name' => 'ICICI', 'transactions' => [...]])]);

// Assert the agent was prompted
StatementParser::assertPrompted(fn ($prompt) => $prompt->contains('bank statement'));

// Assert never called
InvoiceParser::assertNeverPrompted();
```

### Available Attributes

| Attribute | Purpose |
|-----------|---------|
| `#[Provider('mistral')]` | AI provider (or array for failover) |
| `#[Model('mistral-large-latest')]` | Specific model |
| `#[Temperature(0.1)]` | Sampling temperature (0.0–1.0) |
| `#[MaxTokens(8192)]` | Max output tokens |
| `#[MaxSteps(10)]` | Max tool-use iterations |
| `#[Timeout(120)]` | HTTP timeout in seconds |
| `#[UseCheapestModel]` | Auto-select cheapest model |
| `#[UseSmartestModel]` | Auto-select most capable model |

### Key SDK Interfaces

| Interface | Purpose |
|-----------|---------|
| `Agent` | Base contract — `instructions()` method |
| `HasStructuredOutput` | Typed JSON output — `schema()` method |
| `HasTools` | Give agent tools — `tools()` method |
| `Conversational` | Multi-turn conversations — `messages()` method |
| `HasMiddleware` | Logging, rate limiting — `middleware()` method |
