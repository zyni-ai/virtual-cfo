<?php

use App\Models\ImportedFile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imported_files', function (Blueprint $table) {
            $table->text('message_id')->nullable()->after('source_metadata');
        });

        ImportedFile::query()
            ->where('source', 'email')
            ->whereNotNull('source_metadata')
            ->eachById(function (ImportedFile $file) {
                $messageId = $file->source_metadata['message_id'] ?? null;

                if ($messageId !== null) {
                    DB::table('imported_files')
                        ->where('id', $file->id)
                        ->update(['message_id' => $messageId]);
                }
            });

        DB::statement('
            CREATE INDEX imported_files_company_message_id_index
            ON imported_files (company_id, message_id)
            WHERE message_id IS NOT NULL AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS imported_files_company_message_id_index');

        Schema::table('imported_files', function (Blueprint $table) {
            $table->dropColumn('message_id');
        });
    }
};
