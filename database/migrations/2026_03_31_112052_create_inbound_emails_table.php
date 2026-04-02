<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message_id')->nullable();
            $table->text('from_address')->nullable();
            $table->text('subject')->nullable();
            $table->text('body_text')->nullable();
            $table->text('recipient');
            $table->integer('attachment_count')->default(0);
            $table->integer('processed_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->text('status');
            $table->text('rejection_reason')->nullable();
            $table->timestampTz('received_at');
            $table->jsonb('raw_headers')->nullable()->default(null);
            $table->timestampsTz();

            $table->index('company_id');
            $table->index(['company_id', 'message_id']);
            $table->index('status');
            $table->index('received_at');
        });

        DB::statement("ALTER TABLE inbound_emails ADD CONSTRAINT inbound_emails_raw_headers_check CHECK (raw_headers IS NULL OR jsonb_typeof(raw_headers) = 'object')");
    }
};
