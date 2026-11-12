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
        Schema::create('tariff_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // A schedule is in force from this date until the next one starts.
            $table->date('effective_from')->unique();
            $table->timestamps();
        });

        Schema::create('tariff_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_schedule_id')->constrained('tariff_schedules')->cascadeOnDelete();
            $table->foreignId('activity_type_id')->constrained('activity_types');
            $table->enum('scale', array_column(TariffScale::cases(), 'value'))->nullable();
            // Empty is the rate for any role. A removed role's rates can no longer
            // apply to anyone, so they go with it.
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->unsignedInteger('unit_rate_cents');
            $table->unsignedInteger('time_rate_cents')->nullable();
            $table->unsignedSmallInteger('time_block_minutes')->nullable();
            $table->timestamps();

            $table->index(['tariff_schedule_id', 'activity_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tariff_items');
        Schema::dropIfExists('tariff_schedules');
    }
};
