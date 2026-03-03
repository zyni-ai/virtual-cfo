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
        Schema::table('reconciliation_matches', function (Blueprint $table) {
            $table->text('status')->default('confirmed')->after('notes');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('reconciliation_matches', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
