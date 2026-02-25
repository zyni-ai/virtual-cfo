<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('head_mappings', function (Blueprint $table) {
            $table->integer('priority')->nullable()->after('usage_count');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::table('head_mappings', function (Blueprint $table) {
            $table->dropIndex(['priority']);
            $table->dropColumn('priority');
        });
    }
};
