<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imported_files', function (Blueprint $table) {
            $table->text('account_holder_name')->nullable()->after('bank_name');
            $table->decimal('opening_balance', 15, 2)->nullable()->after('statement_period');
        });
    }
};
