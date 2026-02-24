<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Mistral)]
#[Model('mistral-large-latest')]
#[MaxTokens(4096)]
#[Temperature(0.2)]
class HeadMatcher implements Agent, HasStructuredOutput
{
    use Promptable;

    protected string $chartOfAccounts = '';

    public function withChartOfAccounts(string $chartOfAccounts): static
    {
        $this->chartOfAccounts = $chartOfAccounts;

        return $this;
    }

    public function instructions(): Stringable|string
    {
        $base = <<<'INSTRUCTIONS'
        You are an Indian accounting expert familiar with Tally ERP accounting heads.

        Given a list of transaction descriptions from bank/credit card statements and a chart of accounts,
        suggest the most appropriate account head for each transaction.

        Rules:
        - Match based on the nature of the transaction (salary, rent, utilities, vendor payments, etc.)
        - Provide a confidence score between 0 and 1 for each match
        - Provide brief reasoning for each suggestion
        - If no good match exists, suggest the closest head with a low confidence score
        - Consider Indian business context (GST, TDS, etc.)
        INSTRUCTIONS;

        if ($this->chartOfAccounts) {
            $base .= "\n\nAvailable Account Heads:\n".$this->chartOfAccounts;
        }

        return $base;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'matches' => $schema->array()->items([
                'transaction_id' => $schema->integer()->required(),
                'suggested_head_name' => $schema->string()->required(),
                'confidence' => $schema->number()->required(),
                'reasoning' => $schema->string()->required(),
            ])->required(),
        ];
    }
}
