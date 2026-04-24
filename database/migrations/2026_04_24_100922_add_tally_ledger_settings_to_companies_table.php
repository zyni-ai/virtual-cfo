<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->text('tally_input_igst_ledger')->nullable()->after('review_confidence_threshold');
            $table->text('tally_input_cgst_ledger')->nullable()->after('tally_input_igst_ledger');
            $table->text('tally_input_sgst_ledger')->nullable()->after('tally_input_cgst_ledger');
            $table->text('tally_output_igst_ledger')->nullable()->after('tally_input_sgst_ledger');
            $table->text('tally_output_cgst_ledger')->nullable()->after('tally_output_igst_ledger');
            $table->text('tally_output_sgst_ledger')->nullable()->after('tally_output_cgst_ledger');
            $table->text('tally_tds_payable_ledger')->nullable()->after('tally_output_sgst_ledger');
        });
    }
};
