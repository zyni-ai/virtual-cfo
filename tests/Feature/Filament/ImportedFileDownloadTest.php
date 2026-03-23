<?php

use App\Filament\Resources\ImportedFileResource\Pages\ListImportedFiles;
use App\Filament\Resources\ImportedFileResource\Pages\ViewImportedFile;
use App\Models\ImportedFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

describe('ImportedFile Download', function () {
    describe('download route', function () {
        it('allows authenticated user to download a file that exists', function () {
            asUser();

            Storage::disk('local')->put('statements/test-file.pdf', 'fake pdf content');

            $file = ImportedFile::factory()->create([
                'file_path' => 'statements/test-file.pdf',
                'original_filename' => 'HDFC_statement_2024_01.pdf',
            ]);

            $response = $this->get(route('imported-files.download', $file));

            $response->assertOk()
                ->assertHeader('content-type', 'application/pdf')
                ->assertHeader('content-disposition', 'attachment; filename=HDFC_statement_2024_01.pdf');

            Storage::disk('local')->delete('statements/test-file.pdf');
        });

        it('returns correct content-type for PDF files', function () {
            asUser();

            Storage::disk('local')->put('statements/content-type-test.pdf', '%PDF-1.4 fake content');

            $file = ImportedFile::factory()->create([
                'file_path' => 'statements/content-type-test.pdf',
                'original_filename' => 'bank_statement.pdf',
            ]);

            $response = $this->get(route('imported-files.download', $file));

            $response->assertOk()
                ->assertHeader('content-type', 'application/pdf');

            Storage::disk('local')->delete('statements/content-type-test.pdf');
        });

        it('returns 404 when file does not exist on disk', function () {
            asUser();

            $file = ImportedFile::factory()->create([
                'file_path' => 'statements/nonexistent-file.pdf',
                'original_filename' => 'missing.pdf',
            ]);

            $response = $this->get(route('imported-files.download', $file));

            $response->assertNotFound();
        });

        it('prevents unauthenticated user from downloading', function () {
            $file = ImportedFile::factory()->create([
                'file_path' => 'statements/secret.pdf',
                'original_filename' => 'secret.pdf',
            ]);

            $response = $this->get(route('imported-files.download', $file));

            $response->assertRedirect('/admin/login');
        });

        it('allows viewer role to download files', function () {
            $viewer = User::factory()->viewer()->create();
            $this->actingAs($viewer);

            Storage::disk('local')->put('statements/viewer-test.pdf', 'fake pdf content');

            $file = ImportedFile::factory()->create([
                'file_path' => 'statements/viewer-test.pdf',
                'original_filename' => 'viewer_download.pdf',
            ]);

            $response = $this->get(route('imported-files.download', $file));

            $response->assertOk()
                ->assertHeader('content-type', 'application/pdf');

            Storage::disk('local')->delete('statements/viewer-test.pdf');
        });
    });

    describe('soft-deleted records', function () {
        it('allows downloading a soft-deleted file', function () {
            asUser();

            Storage::disk('local')->put('statements/soft-deleted.pdf', 'fake pdf content');

            $file = ImportedFile::factory()->create([
                'file_path' => 'statements/soft-deleted.pdf',
                'original_filename' => 'soft-deleted.pdf',
            ]);

            $file->delete();

            $this->get(route('imported-files.download', $file->id))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf')
                ->assertHeader('content-disposition', 'attachment; filename=soft-deleted.pdf');

            Storage::disk('local')->delete('statements/soft-deleted.pdf');
        });

        it('returns 404 for soft-deleted file when physical file is gone', function () {
            asUser();

            $file = ImportedFile::factory()->create([
                'file_path' => 'statements/gone.pdf',
                'original_filename' => 'gone.pdf',
            ]);

            $file->delete();

            $this->get(route('imported-files.download', $file->id))
                ->assertNotFound();
        });
    });

    describe('mime type detection', function () {
        it('detects application/pdf for PDF files', function () {
            asUser();

            Storage::disk('local')->put('statements/mime-pdf.pdf', '%PDF-1.4 fake pdf');

            $file = ImportedFile::factory()->create([
                'file_path' => 'statements/mime-pdf.pdf',
                'original_filename' => 'mime-pdf.pdf',
            ]);

            $this->get(route('imported-files.download', $file))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');

            Storage::disk('local')->delete('statements/mime-pdf.pdf');
        });
    });

    describe('table download action', function () {
        beforeEach(function () {
            asUser();
        });

        it('has a download action on the table', function () {
            Storage::disk('local')->put('statements/table-action-test.pdf', 'fake pdf');

            $file = ImportedFile::factory()->create([
                'file_path' => 'statements/table-action-test.pdf',
            ]);

            livewire(ListImportedFiles::class)
                ->assertTableActionExists('download');

            Storage::disk('local')->delete('statements/table-action-test.pdf');
        });
    });

    describe('view page download action', function () {
        beforeEach(function () {
            asUser();
        });

        it('has a download action on the view page', function () {
            Storage::disk('local')->put('statements/view-action-test.pdf', 'fake pdf');

            $file = ImportedFile::factory()->create([
                'file_path' => 'statements/view-action-test.pdf',
            ]);

            livewire(ViewImportedFile::class, ['record' => $file->getRouteKey()])
                ->assertActionExists('download');

            Storage::disk('local')->delete('statements/view-action-test.pdf');
        });
    });
});
