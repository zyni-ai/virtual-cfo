<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->text('email');
            $table->text('role');
            $table->text('token')->unique();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->index('company_id');
            $table->index('invited_by');
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX invitations_company_email_pending
            ON invitations (company_id, email)
            WHERE accepted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE invitations
            ADD CONSTRAINT invitations_role_valid
            CHECK (role IN ('admin', 'accountant', 'viewer'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
