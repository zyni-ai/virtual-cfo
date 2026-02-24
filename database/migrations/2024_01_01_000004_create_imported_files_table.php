<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imported_files', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name')->nullable();
            $table->text('account_number')->nullable(); // encrypted
            $table->string('statement_type')->default('bank');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('file_hash', 64)->unique(); // SHA-256
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('mapped_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('bank_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_files');
    }
};
