<?php

declare(strict_types=1);

use App\Enums\Billing\TariffAdjustmentKind;
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
            // A schedule is in force from this date until the next one starts,
            // once it has been published. Until then it is a draft.
            $table->date('effective_from')->unique();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // One line of the gazette: a kind of work and its rate on each scale.
        Schema::create('tariff_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_schedule_id')->constrained('tariff_schedules')->cascadeOnDelete();
            $table->foreignId('activity_type_id')->constrained('activity_types');
            $table->unsignedInteger('scale_a_cents')->nullable();
            $table->unsignedInteger('scale_b_cents')->nullable();
            $table->unsignedInteger('scale_c_cents')->nullable();
            $table->unsignedInteger('scale_d_cents')->nullable();
            $table->unsignedInteger('time_rate_cents')->nullable();
            $table->unsignedSmallInteger('time_block_minutes')->nullable();
            $table->timestamps();

            $table->unique(['tariff_schedule_id', 'activity_type_id']);
        });

        // What a role charges against the gazette. A removed role's markup can
        // no longer apply to anyone, so it goes with it.
        Schema::create('tariff_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_schedule_id')->constrained('tariff_schedules')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->enum('kind', array_column(TariffAdjustmentKind::cases(), 'value'));
            // Hundredths of a percent, or cents, depending on the kind.
            $table->integer('value');
            $table->timestamps();

            $table->unique(['tariff_schedule_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tariff_adjustments');
        Schema::dropIfExists('tariff_items');
        Schema::dropIfExists('tariff_schedules');
    }
};
