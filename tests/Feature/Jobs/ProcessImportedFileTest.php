<?php

use App\Ai\Agents\StatementParser;
use App\Enums\ImportStatus;
use App\Enums\MappingType;
use App\Jobs\MatchTransactionHeads;
use App\Jobs\ProcessImportedFile;
use App\Models\ImportedFile;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

describe('ProcessImportedFile job', function () {
    it('implements ShouldQueue', function () {
        expect(ProcessImportedFile::class)
            ->toImplement(Illuminate\Contracts\Queue\ShouldQueue::class);
    });

    it('sets status to failed with error message on exception', function () {
        $file = ImportedFile::factory()->create(['status' => ImportStatus::Pending]);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg, $ctx) => $msg === 'Failed to process imported file'
                && $ctx['file_id'] === $file->id);

        $job = new ProcessImportedFile($file);

        try {
            $job->handle();
        } catch (\Throwable) {
            // Expected — job rethrows after logging
        }

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Failed)
            ->and($file->error_message)->not->toBeNull();
    });

    it('can be dispatched', function () {
        Queue::fake();

        $file = ImportedFile::factory()->create();

        ProcessImportedFile::dispatch($file);

        Queue::assertPushed(ProcessImportedFile::class, function ($job) use ($file) {
            return $job->importedFile->id === $file->id;
        });
    });

    it('has exponential backoff configured', function () {
        $file = ImportedFile::factory()->create();
        $job = new ProcessImportedFile($file);

        expect($job->backoff())->toBe([30, 120, 300]);
    });

    it('has 600 second timeout', function () {
        expect((new ProcessImportedFile(ImportedFile::factory()->create()))->timeout)->toBe(600);
    });

    it('has 3 tries configured', function () {
        expect((new ProcessImportedFile(ImportedFile::factory()->create()))->tries)->toBe(3);
    });

    it('marks file as failed on permanent failure', function () {
        $file = ImportedFile::factory()->create(['status' => ImportStatus::Processing]);
        $job = new ProcessImportedFile($file);

        $job->failed(new RuntimeException('Test error'));

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Failed)
            ->and($file->error_message)->toContain('permanently failed');
    });
});

describe('ProcessImportedFile with Agent::fake()', function () {
    it('creates transactions and completes file on successful parse', function () {
        Storage::fake('local');
        Storage::put('statements/test.pdf', 'fake-pdf-content');

        StatementParser::fake([
            [
                'bank_name' => 'HDFC Bank',
                'account_number' => '1234567890',
                'statement_period' => '2024-01-01 to 2024-01-31',
                'transactions' => [
                    ['date' => '2024-01-05', 'description' => 'SALARY JAN 2024', 'credit' => 50000, 'balance' => 150000],
                    ['date' => '2024-01-10', 'description' => 'RENT PAYMENT', 'debit' => 15000, 'balance' => 135000],
                    ['date' => '2024-01-15', 'description' => 'EMI HDFC', 'debit' => 8500, 'balance' => 126500],
                ],
            ],
        ]);

        Queue::fake([MatchTransactionHeads::class]);

        $file = ImportedFile::factory()->create([
            'status' => ImportStatus::Pending,
            'file_path' => 'statements/test.pdf',
        ]);

        $job = new ProcessImportedFile($file);
        $job->handle();

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Completed)
            ->and($file->total_rows)->toBe(3)
            ->and($file->mapped_rows)->toBe(0)
            ->and($file->bank_name)->toBe('HDFC Bank')
            ->and($file->account_number)->toBe('1234567890')
            ->and($file->processed_at)->not->toBeNull();

        $transactions = Transaction::where('imported_file_id', $file->id)->get();
        expect($transactions)->toHaveCount(3)
            ->and($transactions->first()->description)->toBe('SALARY JAN 2024')
            ->and($transactions->first()->mapping_type)->toBe(MappingType::Unmapped);
    });

    it('dispatches MatchTransactionHeads on success', function () {
        Storage::fake('local');
        Storage::put('statements/test.pdf', 'fake-pdf-content');

        StatementParser::fake([
            [
                'bank_name' => 'SBI',
                'transactions' => [
                    ['date' => '2024-01-05', 'description' => 'DEPOSIT', 'credit' => 10000, 'balance' => 10000],
                ],
            ],
        ]);

        Queue::fake([MatchTransactionHeads::class]);

        $file = ImportedFile::factory()->create([
            'status' => ImportStatus::Pending,
            'file_path' => 'statements/test.pdf',
        ]);

        $job = new ProcessImportedFile($file);
        $job->handle();

        Queue::assertPushed(MatchTransactionHeads::class, function ($job) use ($file) {
            return $job->importedFile->id === $file->id;
        });
    });

    it('marks file as failed when response has empty transactions', function () {
        Storage::fake('local');
        Storage::put('statements/empty.pdf', 'fake-pdf-content');

        StatementParser::fake([
            [
                'bank_name' => 'ICICI',
                'transactions' => [],
            ],
        ]);

        Queue::fake([MatchTransactionHeads::class]);

        $file = ImportedFile::factory()->create([
            'status' => ImportStatus::Pending,
            'file_path' => 'statements/empty.pdf',
        ]);

        $job = new ProcessImportedFile($file);
        $job->handle();

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Failed)
            ->and($file->error_message)->toContain('No transactions found');

        Queue::assertNotPushed(MatchTransactionHeads::class);
    });

    it('marks file as failed when response is malformed', function () {
        Storage::fake('local');
        Storage::put('statements/bad.pdf', 'fake-pdf-content');

        StatementParser::fake([
            ['bank_name' => 'Unknown Bank'],
        ]);

        $file = ImportedFile::factory()->create([
            'status' => ImportStatus::Pending,
            'file_path' => 'statements/bad.pdf',
        ]);

        Log::shouldReceive('error')->once();
        Log::shouldReceive('warning')->once();

        $job = new ProcessImportedFile($file);

        try {
            $job->handle();
        } catch (\Throwable) {
            // Expected — missing transactions key
        }

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Failed)
            ->and($file->error_message)->not->toBeNull();
    });
});
