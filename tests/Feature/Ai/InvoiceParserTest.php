<?php

use App\Ai\Agents\InvoiceParser;
use Illuminate\Support\Facades\Storage;

describe('InvoiceParser agent', function () {
    it('implements Agent interface', function () {
        expect(InvoiceParser::class)->toImplement(Laravel\Ai\Contracts\Agent::class);
    });

    it('implements HasStructuredOutput', function () {
        expect(InvoiceParser::class)->toImplement(Laravel\Ai\Contracts\HasStructuredOutput::class);
    });

    it('has instructions focused on invoice parsing', function () {
        $agent = new InvoiceParser;
        $instructions = $agent->instructions();

        expect((string) $instructions)->toContain('invoice')
            ->and((string) $instructions)->toContain('GST')
            ->and((string) $instructions)->toContain('vendor');
    });

    it('has schema method for structured output', function () {
        expect(method_exists(InvoiceParser::class, 'schema'))->toBeTrue();
    });

    it('uses the configured parsing model from ai config', function () {
        $agent = new InvoiceParser;

        expect($agent->model())->toBe(config('ai.models.parsing'));
    });

    it('adapts to a custom model when config is changed', function () {
        config()->set('ai.models.parsing', 'mistral-small-latest');

        $agent = new InvoiceParser;

        expect($agent->model())->toBe('mistral-small-latest');
    });
});

describe('InvoiceParser timeout', function () {
    it('has a 180 second timeout', function () {
        $attributes = (new ReflectionClass(InvoiceParser::class))
            ->getAttributes(Laravel\Ai\Attributes\Timeout::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->getArguments()[0])->toBe(180);
    });
});

describe('InvoiceParser with Agent::fake()', function () {
    it('returns structured response with intra-state invoice data', function () {
        Storage::fake('local');
        Storage::put('statements/invoice.pdf', 'fake-pdf-content');

        InvoiceParser::fake([
            [
                'vendor_name' => 'Assetpro Solution Pvt Ltd',
                'vendor_gstin' => '29AAQCA1895C1ZD',
                'invoice_number' => 'ASPL/2439',
                'invoice_date' => '2025-03-25',
                'due_date' => '2025-04-25',
                'place_of_supply' => 'Karnataka',
                'line_items' => [
                    [
                        'description' => 'Office Assistant and Housekeeping charges',
                        'hsn_sac' => '998519',
                        'quantity' => 1,
                        'rate' => 27500.00,
                        'amount' => 27500.00,
                    ],
                ],
                'base_amount' => 27500.00,
                'cgst_rate' => 9,
                'cgst_amount' => 2475.00,
                'sgst_rate' => 9,
                'sgst_amount' => 2475.00,
                'igst_rate' => null,
                'igst_amount' => null,
                'tds_amount' => 550.00,
                'total_amount' => 31900.00,
                'amount_in_words' => 'Thirty One Thousand Nine Hundred Only',
            ],
        ]);

        $response = (new InvoiceParser)->prompt('Parse this vendor invoice.');

        expect($response)->toBeInstanceOf(Laravel\Ai\Responses\StructuredAgentResponse::class)
            ->and($response['vendor_name'])->toBe('Assetpro Solution Pvt Ltd')
            ->and($response['vendor_gstin'])->toBe('29AAQCA1895C1ZD')
            ->and($response['invoice_number'])->toBe('ASPL/2439')
            ->and($response['invoice_date'])->toBe('2025-03-25')
            ->and($response['line_items'])->toHaveCount(1)
            ->and($response['cgst_amount'])->toBe(2475.00)
            ->and($response['sgst_amount'])->toBe(2475.00)
            ->and($response['igst_amount'])->toBeNull()
            ->and($response['tds_amount'])->toBe(550.00)
            ->and($response['total_amount'])->toBe(31900.00);
    });

    it('returns structured response with inter-state invoice data', function () {
        Storage::fake('local');
        Storage::put('statements/invoice-inter.pdf', 'fake-pdf-content');

        InvoiceParser::fake([
            [
                'vendor_name' => 'Tech Solutions Ltd',
                'vendor_gstin' => '07AABCT1234A1Z5',
                'invoice_number' => 'TSL/2024/001',
                'invoice_date' => '2025-02-15',
                'due_date' => '2025-03-15',
                'place_of_supply' => 'Delhi',
                'line_items' => [
                    [
                        'description' => 'Software consulting services',
                        'hsn_sac' => '998311',
                        'quantity' => 1,
                        'rate' => 50000.00,
                        'amount' => 50000.00,
                    ],
                ],
                'base_amount' => 50000.00,
                'cgst_rate' => null,
                'cgst_amount' => null,
                'sgst_rate' => null,
                'sgst_amount' => null,
                'igst_rate' => 18,
                'igst_amount' => 9000.00,
                'tds_amount' => null,
                'total_amount' => 59000.00,
                'amount_in_words' => 'Fifty Nine Thousand Only',
            ],
        ]);

        $response = (new InvoiceParser)->prompt('Parse this vendor invoice.');

        expect($response)->toBeInstanceOf(Laravel\Ai\Responses\StructuredAgentResponse::class)
            ->and($response['vendor_name'])->toBe('Tech Solutions Ltd')
            ->and($response['cgst_amount'])->toBeNull()
            ->and($response['sgst_amount'])->toBeNull()
            ->and($response['igst_rate'])->toBe(18)
            ->and($response['igst_amount'])->toBe(9000.00)
            ->and($response['tds_amount'])->toBeNull()
            ->and($response['total_amount'])->toBe(59000.00);
    });

    it('handles multi-line-item invoices', function () {
        Storage::fake('local');
        Storage::put('statements/invoice-multi.pdf', 'fake-pdf-content');

        InvoiceParser::fake([
            [
                'vendor_name' => 'Office Supplies Co',
                'vendor_gstin' => '29AABCO5678B1Z4',
                'invoice_number' => 'OS/2025/100',
                'invoice_date' => '2025-01-10',
                'due_date' => '2025-02-10',
                'place_of_supply' => 'Karnataka',
                'line_items' => [
                    [
                        'description' => 'Printer Paper A4',
                        'hsn_sac' => '4802',
                        'quantity' => 10,
                        'rate' => 500.00,
                        'amount' => 5000.00,
                    ],
                    [
                        'description' => 'Ink Cartridges',
                        'hsn_sac' => '3215',
                        'quantity' => 5,
                        'rate' => 800.00,
                        'amount' => 4000.00,
                    ],
                    [
                        'description' => 'Stapler Heavy Duty',
                        'hsn_sac' => '8205',
                        'quantity' => 2,
                        'rate' => 1500.00,
                        'amount' => 3000.00,
                    ],
                ],
                'base_amount' => 12000.00,
                'cgst_rate' => 9,
                'cgst_amount' => 1080.00,
                'sgst_rate' => 9,
                'sgst_amount' => 1080.00,
                'igst_rate' => null,
                'igst_amount' => null,
                'tds_amount' => null,
                'total_amount' => 14160.00,
                'amount_in_words' => 'Fourteen Thousand One Hundred and Sixty Only',
            ],
        ]);

        $response = (new InvoiceParser)->prompt('Parse this vendor invoice.');

        expect($response['line_items'])->toHaveCount(3)
            ->and($response['line_items'][0]['description'])->toBe('Printer Paper A4')
            ->and($response['line_items'][1]['description'])->toBe('Ink Cartridges')
            ->and($response['line_items'][2]['description'])->toBe('Stapler Heavy Duty')
            ->and($response['base_amount'])->toBe(12000.00)
            ->and($response['total_amount'])->toBe(14160.00);
    });

    it('tracks that agent was prompted', function () {
        InvoiceParser::fake([
            [
                'vendor_name' => 'Test Vendor',
                'invoice_number' => 'TEST/001',
                'line_items' => [],
                'base_amount' => 0,
                'total_amount' => 0,
            ],
        ]);

        (new InvoiceParser)->prompt('Parse this vendor invoice.');

        InvoiceParser::assertPrompted('Parse this vendor invoice.');
    });

    it('can assert agent was never prompted', function () {
        InvoiceParser::fake([]);

        InvoiceParser::assertNeverPrompted();
    });
});
