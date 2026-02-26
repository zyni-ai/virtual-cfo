<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imported_files', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->index('company_id');
            $table->index('bank_account_id');
        });

        Schema::table('account_heads', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->index('company_id');
        });

        Schema::table('head_mappings', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->index('company_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('head_mappings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('account_heads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('imported_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
