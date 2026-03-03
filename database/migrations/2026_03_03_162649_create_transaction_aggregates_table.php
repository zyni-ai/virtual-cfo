<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('account_head_id')->nullable()->constrained('account_heads')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('credit_card_id')->nullable()->constrained('credit_cards')->nullOnDelete();
            $table->text('year_month');
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->timestampsTz();

            $table->index('company_id');
            $table->index('account_head_id');
            $table->index('bank_account_id');
            $table->index('credit_card_id');
            $table->index('year_month');
        });

        DB::statement('
            CREATE UNIQUE INDEX transaction_aggregates_unique
            ON transaction_aggregates (
                company_id,
                COALESCE(account_head_id, 0),
                COALESCE(bank_account_id, 0),
                COALESCE(credit_card_id, 0),
                year_month
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_aggregates');
    }
};
