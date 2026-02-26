<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('mistral')]
#[MaxTokens(8192)]
#[Temperature(0.1)]
#[Timeout(180)]
class InvoiceParser implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the model to use for invoice parsing.
     */
    public function model(): string
    {
        return config('ai.models.parsing', 'mistral-large-latest');
    }

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        You are a financial document parser specializing in vendor invoices for Indian businesses.

        When given a PDF invoice, extract ALL fields with the following rules:
        - Extract vendor details: name, GSTIN, invoice number, dates
        - Identify the place of supply to determine GST type (intra-state vs inter-state)
        - For intra-state supply: extract CGST and SGST rates and amounts
        - For inter-state supply: extract IGST rate and amount
        - Extract TDS amount if deducted
        - Parse ALL line items with description, HSN/SAC code, quantity, rate, and amount
        - Dates should be in YYYY-MM-DD format
        - Amounts should be numeric (no currency symbols or commas)
        - If a field is not present, use null
        - Calculate and verify: base_amount + GST - TDS should approximately equal total_amount

        Be thorough — accuracy is critical for GST filing and Tally journal entries.
        INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'vendor_name' => $schema->string()->required(),
            'vendor_gstin' => $schema->string(),
            'invoice_number' => $schema->string()->required(),
            'invoice_date' => $schema->string()->required(),
            'due_date' => $schema->string(),
            'place_of_supply' => $schema->string(),
            'line_items' => $schema->array()->items($schema->object([
                'description' => $schema->string()->required(),
                'hsn_sac' => $schema->string(),
                'quantity' => $schema->number(),
                'rate' => $schema->number(),
                'amount' => $schema->number()->required(),
            ]))->required(),
            'base_amount' => $schema->number()->required(),
            'cgst_rate' => $schema->number(),
            'cgst_amount' => $schema->number(),
            'sgst_rate' => $schema->number(),
            'sgst_amount' => $schema->number(),
            'igst_rate' => $schema->number(),
            'igst_amount' => $schema->number(),
            'tds_amount' => $schema->number(),
            'total_amount' => $schema->number()->required(),
            'amount_in_words' => $schema->string(),
        ];
    }
}
