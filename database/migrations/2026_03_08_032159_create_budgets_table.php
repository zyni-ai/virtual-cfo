<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_head_id')->constrained()->cascadeOnDelete();
            $table->text('period_type'); // monthly, quarterly, annual
            $table->decimal('amount', 15, 2);
            $table->text('year_month')->nullable(); // '2026-03' for monthly, '2026-Q1' for quarterly
            $table->text('financial_year'); // '2025-26'
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('company_id');
            $table->index('account_head_id');
            $table->unique(['company_id', 'account_head_id', 'period_type', 'year_month', 'financial_year'], 'budgets_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
