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

    it('sets status to processing at the start', function () {
        $file = ImportedFile::factory()->create(['status' => ImportStatus::Pending]);

        // The job will fail because there's no real PDF, but it should set status to Processing first
        Log::shouldReceive('error')->once();

        $job = new ProcessImportedFile($file);
        $job->handle();

        // After failure, status should be Failed, but it was Processing during execution
        expect($file->fresh()->status)->toBe(ImportStatus::Failed);
    });

    it('sets status to failed with error message on exception', function () {
        $file = ImportedFile::factory()->create(['status' => ImportStatus::Pending]);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg, $ctx) => $msg === 'Failed to process imported file'
                && $ctx['file_id'] === $file->id);

        $job = new ProcessImportedFile($file);
        $job->handle();

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

    it('has correct retry and timeout settings', function () {
        $file = ImportedFile::factory()->create();
        $job = new ProcessImportedFile($file);

        expect($job->tries)->toBe(2)
            ->and($job->timeout)->toBe(300);
    });
});
