<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('ip_address');
            $table->text('user_agent');
            $table->text('device_type')->nullable();
            $table->text('browser')->nullable();
            $table->text('os')->nullable();
            $table->timestampTz('logged_in_at');
            $table->timestampTz('last_active_at');
            $table->timestampTz('logged_out_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'logged_in_at']);
        });

        DB::statement(<<<'SQL'
            CREATE INDEX login_sessions_user_active
            ON login_sessions (user_id) WHERE logged_out_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('login_sessions');
    }
};
