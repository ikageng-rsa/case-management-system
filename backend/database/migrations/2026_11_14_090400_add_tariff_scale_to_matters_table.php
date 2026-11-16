<?php

declare(strict_types=1);

use App\Enums\Billing\TariffScale;
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
        Schema::table('matters', function (Blueprint $table) {
            // The scale a bill of cost on the matter starts on, once it is known
            // from the claim or the costs order. A bill can still be drawn on another.
            $table->enum('tariff_scale', array_column(TariffScale::cases(), 'value'))->nullable()->after('court_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matters', function (Blueprint $table) {
            $table->dropColumn('tariff_scale');
        });
    }
};
