<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->text('name');
            $table->text('account_number')->nullable();
            $table->text('ifsc_code')->nullable();
            $table->text('branch')->nullable();
            $table->text('account_type')->default('current');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('company_id');
        });

        // Partial unique index for active (non-deleted) bank accounts
        DB::statement('CREATE UNIQUE INDEX bank_accounts_company_name_number_unique ON bank_accounts (company_id, name, account_number) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
