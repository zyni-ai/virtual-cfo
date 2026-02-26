<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->text('reconciliation_status')->default('unreconciled');
            $table->index('reconciliation_status');
        });

        Schema::create('reconciliation_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();
            $table->foreignId('invoice_transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();
            $table->float('confidence');
            $table->text('match_method');
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('bank_transaction_id');
            $table->index('invoice_transaction_id');
            $table->index('match_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_matches');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['reconciliation_status']);
            $table->dropColumn('reconciliation_status');
        });
    }
};
