<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->text('provider');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestampTz('token_expires_at')->nullable();
            $table->text('settings')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('company_id');
        });

        DB::statement('CREATE UNIQUE INDEX connectors_company_provider_unique ON connectors (company_id, provider) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('connectors');
    }
};
