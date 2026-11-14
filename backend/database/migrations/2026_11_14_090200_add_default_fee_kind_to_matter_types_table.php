<?php

declare(strict_types=1);

use App\Enums\Billing\FeeAgreementKind;
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
        Schema::table('matter_types', function (Blueprint $table) {
            // The fee agreement a matter of this type is suggested to start with.
            $table->enum('default_fee_kind', array_column(FeeAgreementKind::cases(), 'value'))
                ->default(FeeAgreementKind::Mandate->value)
                ->after('is_litigation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matter_types', function (Blueprint $table) {
            $table->dropColumn('default_fee_kind');
        });
    }
};
