<?php

use App\Ai\Agents\HeadMatcher;
use App\Ai\Agents\StatementParser;

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
});
