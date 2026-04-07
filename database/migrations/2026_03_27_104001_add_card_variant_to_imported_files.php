<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imported_files', function (Blueprint $table) {
            // Card product variant extracted from the document (e.g. "Platinum", "Ruby", "Regalia")
            $table->text('card_variant')->nullable()->after('bank_name');
        });
    }
};
