<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('account_holder_name')->nullable();
            $table->text('date_of_birth')->nullable();
            $table->text('pan_number')->nullable();
            $table->text('mobile_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['account_holder_name', 'date_of_birth', 'pan_number', 'mobile_number']);
        });
    }
};
