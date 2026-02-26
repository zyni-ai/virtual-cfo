<?php

use App\Ai\Agents\HeadMatcher;
use App\Ai\Agents\StatementParser;
use Illuminate\Support\Facades\Storage;

describe('AI provider configuration', function () {
    it('has an openrouter provider configured', function () {
        $config = config('ai.providers.openrouter');

        expect($config)->not->toBeNull()
            ->and($config['driver'])->toBe('openai')
            ->and($config)->toHaveKey('url');
    });

    it('keeps mistral provider for OCR', function () {
        expect(config('ai.providers.mistral'))->not->toBeNull()
            ->and(config('ai.providers.mistral.driver'))->toBe('mistral');
    });
});

describe('StatementParser agent', function () {
    it('implements Agent interface', function () {
        expect(StatementParser::class)->toImplement(Laravel\Ai\Contracts\Agent::class);
    });

    it('implements HasStructuredOutput', function () {
        expect(StatementParser::class)->toImplement(Laravel\Ai\Contracts\HasStructuredOutput::class);
    });

    it('has instructions', function () {
        $agent = new StatementParser;
        $instructions = $agent->instructions();

        expect((string) $instructions)->toContain('financial document parser')
            ->and((string) $instructions)->toContain('transaction');
    });

    it('has HasStructuredOutput schema method', function () {
        expect(method_exists(StatementParser::class, 'schema'))->toBeTrue();
    });

    it('uses the configured model from ai config', function () {
        $agent = new StatementParser;

        expect($agent->model())->toBe(config('ai.models.parsing'));
    });

    it('adapts to a custom model when config is changed', function () {
        config()->set('ai.models.parsing', 'mistral-small-latest');

        $agent = new StatementParser;

        expect($agent->model())->toBe('mistral-small-latest');
    });
});

describe('StatementParser provider', function () {
    it('uses the openrouter provider', function () {
        $attributes = (new ReflectionClass(StatementParser::class))
            ->getAttributes(Laravel\Ai\Attributes\Provider::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->getArguments()[0])->toBe('openrouter');
    });

    it('can be resolved via container using make()', function () {
        $agent = StatementParser::make();

        expect($agent)->toBeInstanceOf(StatementParser::class);
    });
});

describe('StatementParser timeout', function () {
    it('has a 300 second timeout', function () {
        $attributes = (new ReflectionClass(StatementParser::class))
            ->getAttributes(Laravel\Ai\Attributes\Timeout::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->getArguments()[0])->toBe(300);
    });
});

describe('HeadMatcher agent', function () {
    it('implements Agent interface', function () {
        expect(HeadMatcher::class)->toImplement(Laravel\Ai\Contracts\Agent::class);
    });

    it('implements HasStructuredOutput', function () {
        expect(HeadMatcher::class)->toImplement(Laravel\Ai\Contracts\HasStructuredOutput::class);
    });

    it('has instructions', function () {
        $agent = new HeadMatcher;
        $instructions = $agent->instructions();

        expect((string) $instructions)->toContain('accounting expert')
            ->and((string) $instructions)->toContain('account head');
    });

    it('can set chart of accounts', function () {
        $agent = new HeadMatcher;
        $result = $agent->withChartOfAccounts("1: Salary\n2: Rent");

        expect($result)->toBeInstanceOf(HeadMatcher::class);
        expect((string) $result->instructions())->toContain('Salary')
            ->and((string) $result->instructions())->toContain('Rent');
    });

    it('has HasStructuredOutput schema method', function () {
        expect(method_exists(HeadMatcher::class, 'schema'))->toBeTrue();
    });

    it('uses the configured model from ai config', function () {
        $agent = new HeadMatcher;

        expect($agent->model())->toBe(config('ai.models.matching'));
    });

    it('adapts to a custom model when config is changed', function () {
        config()->set('ai.models.matching', 'codestral-latest');

        $agent = new HeadMatcher;

        expect($agent->model())->toBe('codestral-latest');
    });

    it('has a 120 second timeout', function () {
        $attributes = (new ReflectionClass(HeadMatcher::class))
            ->getAttributes(Laravel\Ai\Attributes\Timeout::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->getArguments()[0])->toBe(120);
    });

    it('uses the openrouter provider', function () {
        $attributes = (new ReflectionClass(HeadMatcher::class))
            ->getAttributes(Laravel\Ai\Attributes\Provider::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->getArguments()[0])->toBe('openrouter');
    });

    it('can be resolved via container using make()', function () {
        $agent = HeadMatcher::make();

        expect($agent)->toBeInstanceOf(HeadMatcher::class);
    });
});

describe('StatementParser with Agent::fake()', function () {
    it('returns structured response with faked data', function () {
        Storage::fake('local');
        Storage::put('statements/test.pdf', 'fake-pdf-content');

        StatementParser::fake([
            [
                'bank_name' => 'HDFC Bank',
                'account_number' => '1234567890',
                'statement_period' => '2024-01-01 to 2024-01-31',
                'transactions' => [
                    ['date' => '2024-01-05', 'description' => 'SALARY', 'credit' => 50000, 'balance' => 150000],
                ],
            ],
        ]);

        $response = (new StatementParser)->prompt('Parse this statement.');

        expect($response)->toBeInstanceOf(Laravel\Ai\Responses\StructuredAgentResponse::class)
            ->and($response['bank_name'])->toBe('HDFC Bank')
            ->and($response['transactions'])->toHaveCount(1)
            ->and($response['transactions'][0]['description'])->toBe('SALARY');
    });

    it('tracks that agent was prompted', function () {
        StatementParser::fake([
            ['bank_name' => 'SBI', 'transactions' => []],
        ]);

        (new StatementParser)->prompt('Parse this statement.');

        StatementParser::assertPrompted('Parse this statement.');
    });

    it('can assert agent was never prompted', function () {
        StatementParser::fake([]);

        StatementParser::assertNeverPrompted();
    });
});

describe('HeadMatcher with Agent::fake()', function () {
    it('returns structured response with match data', function () {
        HeadMatcher::fake([
            [
                'matches' => [
                    [
                        'transaction_id' => 1,
                        'suggested_head_id' => 10,
                        'suggested_head_name' => 'Salary',
                        'confidence' => 0.95,
                        'reasoning' => 'Description contains SALARY keyword',
                    ],
                ],
            ],
        ]);

        $response = (new HeadMatcher)
            ->withChartOfAccounts('10: Salary (Income)')
            ->prompt('Match these transactions.');

        expect($response)->toBeInstanceOf(Laravel\Ai\Responses\StructuredAgentResponse::class)
            ->and($response['matches'])->toHaveCount(1)
            ->and($response['matches'][0]['suggested_head_id'])->toBe(10)
            ->and($response['matches'][0]['confidence'])->toBe(0.95);
    });

    it('tracks that agent was prompted', function () {
        HeadMatcher::fake([
            ['matches' => []],
        ]);

        (new HeadMatcher)->prompt('Match these transactions.');

        HeadMatcher::assertPrompted('Match these transactions.');
    });
});
