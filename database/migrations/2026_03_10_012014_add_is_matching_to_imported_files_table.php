<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('imported_files', function (Blueprint $table) {
            $table->boolean('is_matching')->default(false)->after('status');
            $table->index('is_matching', 'imported_files_is_matching_idx');
        });
    }

    public function down(): void
    {
        Schema::table('imported_files', function (Blueprint $table) {
            $table->dropIndex('imported_files_is_matching_idx');
            $table->dropColumn('is_matching');
        });
    }
};
