<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imported_files', function (Blueprint $table) {
            $table->foreignId('inbound_email_id')->nullable()->constrained('inbound_emails')->nullOnDelete()->after('company_id');
            $table->index('inbound_email_id');
        });
    }
};
