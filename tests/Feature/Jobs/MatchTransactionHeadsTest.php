<?php

use App\Jobs\MatchTransactionHeads;
use App\Models\AccountHead;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Models\TransactionAggregate;
use App\Services\AggregateService;
use App\Services\HeadMatcher\HeadMatcherService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

describe('MatchTransactionHeads job', function () {
    it('calls HeadMatcherService::matchForFile', function () {
        $file = ImportedFile::factory()->create();

        $service = Mockery::mock(HeadMatcherService::class);
        $service->shouldReceive('matchForFile')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->id === $file->id))
            ->andReturn(['rule_matched' => 2, 'ai_matched' => 1, 'unmatched' => 0]);

        $job = new MatchTransactionHeads($file);
        $job->handle($service, app(AggregateService::class));
    });

    it('logs results after matching', function () {
        $file = ImportedFile::factory()->create();

        Log::shouldReceive('info')
            ->withArgs(fn ($msg, $context) => $msg === 'Head matching completed'
                && $context['file_id'] === $file->id
                && $context['rule_matched'] === 5)
            ->once();
        Log::shouldReceive('info')->withAnyArgs();

        $service = Mockery::mock(HeadMatcherService::class);
        $service->shouldReceive('matchForFile')
            ->andReturn(['rule_matched' => 5, 'ai_matched' => 0, 'unmatched' => 3]);

        $job = new MatchTransactionHeads($file);
        $job->handle($service, app(AggregateService::class));
    });

    it('logs and rethrows errors', function () {
        $file = ImportedFile::factory()->create();

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg) => $msg === 'Failed to match transaction heads');

        $service = Mockery::mock(HeadMatcherService::class);
        $service->shouldReceive('matchForFile')
            ->andThrow(new RuntimeException('AI service unavailable'));

        $job = new MatchTransactionHeads($file);

        expect(fn () => $job->handle($service, app(AggregateService::class)))->toThrow(RuntimeException::class, 'AI service unavailable');
    });

    it('implements ShouldQueue', function () {
        expect(MatchTransactionHeads::class)
            ->toImplement(ShouldQueue::class);
    });

    it('has exponential backoff configured', function () {
        $file = ImportedFile::factory()->create();
        $job = new MatchTransactionHeads($file);

        expect($job->backoff())->toBe([30, 120, 300]);
    });

    it('has 600 second timeout', function () {
        expect((new MatchTransactionHeads(ImportedFile::factory()->create()))->timeout)->toBe(600);
    });

    it('has 3 tries configured', function () {
        expect((new MatchTransactionHeads(ImportedFile::factory()->create()))->tries)->toBe(3);
    });
});

describe('MatchTransactionHeads aggregate rebuild', function () {
    it('rebuilds TransactionAggregate after matching so reports reflect real account heads', function () {
        asUser();
        $company = tenant();
        $head = AccountHead::factory()->create(['company_id' => $company->id]);
        $file = ImportedFile::factory()->create(['company_id' => $company->id]);

        // Step 1: Create transactions (observer fires → aggregates written with null account_head_id)
        $transactions = Transaction::factory()->count(2)->debit(3000)->create([
            'company_id' => $company->id,
            'imported_file_id' => $file->id,
            'account_head_id' => null,
            'date' => '2025-04-10',
        ]);

        // Confirm stale aggregate state: null head
        expect(TransactionAggregate::where('company_id', $company->id)
            ->whereNull('account_head_id')
            ->where('year_month', '2025-04')
            ->exists()
        )->toBeTrue();

        // Step 2: Simulate bulk head matching — bypasses observer, aggregates stay stale
        Transaction::where('imported_file_id', $file->id)
            ->update(['account_head_id' => $head->id]);

        // Aggregates still show null head (the bug)
        expect(TransactionAggregate::where('company_id', $company->id)
            ->where('account_head_id', $head->id)
            ->exists()
        )->toBeFalse();

        // Step 3: Run the job (mock service so AI doesn't actually run)
        $service = Mockery::mock(HeadMatcherService::class);
        $service->shouldReceive('matchForFile')
            ->once()
            ->andReturn(['rule_matched' => 2, 'ai_matched' => 0, 'unmatched' => 0]);

        $job = new MatchTransactionHeads($file);
        $job->handle($service, app(AggregateService::class));

        // Step 4: Aggregates should now reflect the real account head
        expect(TransactionAggregate::where('company_id', $company->id)
            ->where('account_head_id', $head->id)
            ->where('year_month', '2025-04')
            ->exists()
        )->toBeTrue();
    });

    it('does not rebuild aggregates when matching throws an exception', function () {
        asUser();
        $company = tenant();
        $head = AccountHead::factory()->create(['company_id' => $company->id]);
        $file = ImportedFile::factory()->create(['company_id' => $company->id]);

        Transaction::factory()->debit(1000)->create([
            'company_id' => $company->id,
            'imported_file_id' => $file->id,
            'account_head_id' => null,
            'date' => '2025-04-10',
        ]);

        // Pre-assign head via bulk update (stale state)
        Transaction::where('imported_file_id', $file->id)
            ->update(['account_head_id' => $head->id]);

        $service = Mockery::mock(HeadMatcherService::class);
        $service->shouldReceive('matchForFile')
            ->andThrow(new RuntimeException('AI service unavailable'));

        $aggregateService = Mockery::mock(AggregateService::class);
        $aggregateService->shouldNotReceive('rebuildForFile');

        Log::shouldReceive('error')->once();

        $job = new MatchTransactionHeads($file);

        expect(fn () => $job->handle($service, $aggregateService))->toThrow(RuntimeException::class);

        // Aggregate rebuild should NOT have run — null-head aggregate still present
        expect(TransactionAggregate::where('company_id', $company->id)
            ->whereNull('account_head_id')
            ->where('year_month', '2025-04')
            ->exists()
        )->toBeTrue();
    });
});
