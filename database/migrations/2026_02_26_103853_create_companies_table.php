<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->text('name');
            $table->text('gstin')->nullable();
            $table->text('state')->nullable();
            $table->text('gst_registration_type')->default('Regular');
            $table->text('financial_year')->nullable();
            $table->text('currency')->default('INR');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
