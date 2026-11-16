<?php

declare(strict_types=1);

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
        Schema::table('bill_lines', function (Blueprint $table) {
            // The finalised agreement a fee bill's line charges the share of, kept after a void clears the claim.
            $table->foreignId('fee_agreement_id')->nullable()->after('disbursement_id')->constrained('fee_agreements')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fee_agreement_id');
        });
    }
};
