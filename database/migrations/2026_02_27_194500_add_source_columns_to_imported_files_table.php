<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imported_files', function (Blueprint $table) {
            $table->text('source')->default('manual_upload')->after('status');
            $table->text('source_metadata')->nullable()->after('source');

            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('imported_files', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'source_metadata']);
        });
    }
};
