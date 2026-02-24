<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_heads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('account_heads')->nullOnDelete();
            $table->string('tally_guid')->nullable();
            $table->string('group_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('group_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_heads');
    }
};
