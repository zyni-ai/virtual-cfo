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
        Schema::table('users', function (Blueprint $table) {
            $table->jsonb('toured_pages')->nullable();
        });

        if (Schema::hasColumn('users', 'toured_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('toured_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('toured_pages');
        });
    }
};
