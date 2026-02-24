<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_heads', function (Blueprint $table) {
            $table->unique(['name', 'group_name'], 'account_heads_name_group_unique');
        });
    }

    public function down(): void
    {
        Schema::table('account_heads', function (Blueprint $table) {
            $table->dropUnique('account_heads_name_group_unique');
        });
    }
};
