<?php

use App\Enums\ImportStatus;
use App\Jobs\ProcessImportedFile;
use App\Models\ImportedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

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
