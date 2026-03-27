<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('openrouter')]
#[MaxTokens(8192)]
#[Temperature(0.1)]
#[Timeout(300)]
class StatementParser implements Agent, HasMiddleware, HasStructuredOutput
{
    use Promptable;

    /**
     * @return array<int, \App\Ai\Middleware\AuditLlmCalls>
     */
    public function middleware(): array
    {
        return [
            new \App\Ai\Middleware\AuditLlmCalls,
        ];
    }

    /**
     * Get the model to use for statement parsing.
     */
    public function model(): string
    {
        return config('ai.models.parsing', 'mistralai/mistral-large-latest');
    }

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        You are a financial document parser specializing in bank and credit card statements.

        When given a PDF statement, extract ALL transactions with the following rules:
        - Parse every transaction row in the statement
        - Detect the bank name and account number from the header/footer
        - Identify the statement period (start and end dates)
        - For each transaction, extract: date, description, debit amount, credit amount, and running balance
        - Dates should be in YYYY-MM-DD format
        - Amounts should be numeric (no currency symbols or commas)
        - If a field is not present, use null
        - Extract reference numbers where available
        - Handle multi-line transaction descriptions by concatenating them

        For credit card statements, also extract the Previous Balance (opening balance) from the Statement Summary section at the top of the statement. This is labelled "Previous Balance", "Opening Balance", or similar — it is NOT a transaction row. Set it as `previous_balance` in the response.

        Be thorough — do not skip any transactions. Accuracy is critical for accounting purposes.
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'bank_name' => $schema->string()->required(),
            'account_number' => $schema->string(),
            'statement_period' => $schema->string(),
            'previous_balance' => $schema->number(),
            'transactions' => $schema->array()->items($schema->object([
                'date' => $schema->string()->required(),
                'description' => $schema->string()->required(),
                'reference' => $schema->string(),
                'debit' => $schema->number(),
                'credit' => $schema->number(),
                'balance' => $schema->number(),
            ]))->required(),
        ];
    }
}
