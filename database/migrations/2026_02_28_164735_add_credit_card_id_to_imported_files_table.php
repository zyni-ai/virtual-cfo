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
            $table->foreignId('credit_card_id')->nullable()->index()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imported_files', function (Blueprint $table) {
            $table->dropForeign(['credit_card_id']);
            $table->dropColumn('credit_card_id');
        });
    }
};
