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
        Schema::create('recurring_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->text('description_pattern');
            $table->text('bank_format')->nullable();
            $table->foreignId('account_head_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('avg_amount', 15, 2)->nullable();
            $table->text('frequency')->default('monthly');
            $table->integer('occurrence_count')->default(0);
            $table->date('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('company_id');
            $table->index('account_head_id');
            $table->unique(['company_id', 'description_pattern', 'bank_format'], 'recurring_patterns_company_pattern_bank_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_patterns');
    }
};
