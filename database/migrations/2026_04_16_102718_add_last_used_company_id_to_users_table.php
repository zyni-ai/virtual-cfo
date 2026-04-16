<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('last_used_company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete()
                ->after('dismissed_suggestions');

            $table->index('last_used_company_id');
        });
    }
};
