<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('inbox_address')->nullable()->after('currency');
        });

        DB::statement('CREATE UNIQUE INDEX companies_inbox_address_unique ON companies (inbox_address) WHERE inbox_address IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS companies_inbox_address_unique');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('inbox_address');
        });
    }
};
