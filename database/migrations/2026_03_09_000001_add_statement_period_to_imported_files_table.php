<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imported_files', function (Blueprint $table) {
            $table->text('statement_period')->nullable()->after('account_number');
        });
    }

    public function down(): void
    {
        Schema::table('imported_files', function (Blueprint $table) {
            $table->dropColumn('statement_period');
        });
    }
};
