<?php

use App\Models\ImportedFile;
use Illuminate\Support\Facades\Storage;

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

describe('ImportedFile deleting event', function () {
    it('deletes the file from storage when model is deleted', function () {
        Storage::fake('local');
        Storage::disk('local')->put('statements/test.pdf', 'content');

        $file = ImportedFile::factory()->create(['file_path' => 'statements/test.pdf']);
        $file->delete();

        Storage::disk('local')->assertMissing('statements/test.pdf');
    });

    it('does not fail when file does not exist on disk', function () {
        Storage::fake('local');

        $file = ImportedFile::factory()->create(['file_path' => 'statements/nonexistent.pdf']);
        $file->delete();

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
