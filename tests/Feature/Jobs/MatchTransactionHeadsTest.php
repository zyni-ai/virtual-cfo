<?php

use App\Jobs\MatchTransactionHeads;
use App\Models\ImportedFile;
use App\Services\HeadMatcher\HeadMatcherService;
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
        $job->handle($service);
    });

    it('logs results after matching', function () {
        $file = ImportedFile::factory()->create();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg, $context) => $msg === 'Head matching completed'
                && $context['file_id'] === $file->id
                && $context['rule_matched'] === 5);

        $service = Mockery::mock(HeadMatcherService::class);
        $service->shouldReceive('matchForFile')
            ->andReturn(['rule_matched' => 5, 'ai_matched' => 0, 'unmatched' => 3]);

        $job = new MatchTransactionHeads($file);
        $job->handle($service);
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

        expect(fn () => $job->handle($service))->toThrow(RuntimeException::class, 'AI service unavailable');
    });

    it('implements ShouldQueue', function () {
        expect(MatchTransactionHeads::class)
            ->toImplement(Illuminate\Contracts\Queue\ShouldQueue::class);
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
