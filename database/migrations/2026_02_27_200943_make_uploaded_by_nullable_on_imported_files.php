<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE imported_files ALTER COLUMN uploaded_by DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE imported_files ALTER COLUMN uploaded_by SET NOT NULL');
    }
};
