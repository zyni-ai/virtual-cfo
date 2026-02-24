<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('head_mappings', function (Blueprint $table) {
            $table->id();
            $table->text('pattern');
            $table->string('match_type')->default('contains');
            $table->foreignId('account_head_id')->constrained('account_heads')->cascadeOnDelete();
            $table->string('bank_name')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index('account_head_id');
            $table->index('bank_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('head_mappings');
    }
};
