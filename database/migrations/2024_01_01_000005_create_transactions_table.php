<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imported_file_id')->constrained('imported_files')->cascadeOnDelete();
            $table->date('date');
            $table->text('description'); // encrypted
            $table->string('reference_number')->nullable();
            $table->text('debit')->nullable(); // encrypted decimal
            $table->text('credit')->nullable(); // encrypted decimal
            $table->text('balance')->nullable(); // encrypted decimal
            $table->foreignId('account_head_id')->nullable()->constrained('account_heads')->nullOnDelete();
            $table->string('mapping_type')->default('unmapped');
            $table->float('ai_confidence')->nullable();
            $table->text('raw_data')->nullable(); // encrypted JSONB
            $table->string('bank_format')->nullable();
            $table->timestamps();

            $table->index('imported_file_id');
            $table->index('account_head_id');
            $table->index('mapping_type');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
