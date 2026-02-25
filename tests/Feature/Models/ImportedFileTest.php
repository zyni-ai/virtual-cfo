<?php

use App\Models\ImportedFile;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

describe('ImportedFile soft deletes', function () {
    it('uses the SoftDeletes trait', function () {
        expect(in_array(SoftDeletes::class, class_uses_recursive(ImportedFile::class)))->toBeTrue();
    });

    it('is excluded from normal queries after soft delete', function () {
        $file = ImportedFile::factory()->create();

        $file->delete();

        expect(ImportedFile::find($file->id))->toBeNull();
    });

    it('can be restored after soft delete', function () {
        $file = ImportedFile::factory()->create();
        $file->delete();

        $file->restore();

        expect(ImportedFile::find($file->id))->not->toBeNull();
    });

    it('is permanently removed after force delete', function () {
        $file = ImportedFile::factory()->create();

        $file->forceDelete();

        expect(ImportedFile::withTrashed()->find($file->id))->toBeNull();
    });

    it('is included in withTrashed queries after soft delete', function () {
        $file = ImportedFile::factory()->create();
        $file->delete();

        expect(ImportedFile::withTrashed()->find($file->id))->not->toBeNull();
        expect(ImportedFile::withTrashed()->find($file->id)->trashed())->toBeTrue();
    });

    it('does not delete file from storage on soft delete', function () {
        Storage::fake('local');
        Storage::disk('local')->put('statements/test.pdf', 'content');

        $file = ImportedFile::factory()->create(['file_path' => 'statements/test.pdf']);
        $file->delete();

        Storage::disk('local')->assertExists('statements/test.pdf');
    });

    it('deletes file from storage on force delete', function () {
        Storage::fake('local');
        Storage::disk('local')->put('statements/test.pdf', 'content');

        $file = ImportedFile::factory()->create(['file_path' => 'statements/test.pdf']);
        $file->forceDelete();

        Storage::disk('local')->assertMissing('statements/test.pdf');
    });
});

describe('ImportedFile cascade soft deletes', function () {
    it('soft-deletes associated transactions when soft-deleted', function () {
        $file = ImportedFile::factory()->create();
        $transactions = Transaction::factory()->for($file, 'importedFile')->count(3)->create();

        $file->delete();

        foreach ($transactions as $transaction) {
            expect(Transaction::find($transaction->id))->toBeNull();
            expect(Transaction::withTrashed()->find($transaction->id)->trashed())->toBeTrue();
        }
    });

    it('restores associated transactions when restored', function () {
        $file = ImportedFile::factory()->create();
        $transactions = Transaction::factory()->for($file, 'importedFile')->count(3)->create();
        $file->delete();

        $file->restore();

        foreach ($transactions as $transaction) {
            expect(Transaction::find($transaction->id))->not->toBeNull();
        }
    });
});

describe('ImportedFile::mappedPercentage', function () {
    it('returns 0 when total_rows is zero', function () {
        $file = ImportedFile::factory()->create(['total_rows' => 0, 'mapped_rows' => 0]);

        expect($file->mapped_percentage)->toBe(0.0);
    });

    it('calculates percentage correctly', function () {
        $file = ImportedFile::factory()->completed(totalRows: 100, mappedRows: 60)->create();

        expect($file->mapped_percentage)->toBe(60.0);
    });

    it('rounds to one decimal place', function () {
        $file = ImportedFile::factory()->completed(totalRows: 3, mappedRows: 1)->create();

        expect($file->mapped_percentage)->toBe(33.3);
    });

    it('returns 100 when all rows are mapped', function () {
        $file = ImportedFile::factory()->completed(totalRows: 50, mappedRows: 50)->create();

        expect($file->mapped_percentage)->toBe(100.0);
    });
});

describe('ImportedFile forceDeleting event', function () {
    it('deletes the file from storage when model is force-deleted', function () {
        Storage::fake('local');
        Storage::disk('local')->put('statements/test.pdf', 'content');

        $file = ImportedFile::factory()->create(['file_path' => 'statements/test.pdf']);
        $file->forceDelete();

        Storage::disk('local')->assertMissing('statements/test.pdf');
    });

    it('does not fail when file does not exist on disk during force delete', function () {
        Storage::fake('local');

        $file = ImportedFile::factory()->create(['file_path' => 'statements/nonexistent.pdf']);
        $file->forceDelete();

        expect(true)->toBeTrue(); // No exception thrown
    });
});

describe('ImportedFile relationships', function () {
    it('belongs to an uploader', function () {
        $file = ImportedFile::factory()->create();

        expect($file->uploader)->not->toBeNull();
    });

    it('has many transactions', function () {
        $file = ImportedFile::factory()->create();

        expect($file->transactions)->toBeEmpty();
    });
});

describe('ImportedFile encryption', function () {
    it('encrypts and decrypts account_number', function () {
        $file = ImportedFile::factory()->create(['account_number' => '1234567890']);

        $fresh = ImportedFile::find($file->id);
        expect($fresh->account_number)->toBe('1234567890');
    });
});
