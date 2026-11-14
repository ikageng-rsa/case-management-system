<?php

declare(strict_types=1);

use App\Enums\Billing\FeeAgreementKind;
use App\Enums\Billing\FeeAgreementStatus;
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
        Schema::create('fee_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('matter_id')->constrained('matters')->cascadeOnDelete();
            $table->enum('kind', array_column(FeeAgreementKind::cases(), 'value'));
            // Hundredths of a percent, as it was agreed; empty under a mandate.
            $table->unsignedInteger('percent_hundredths')->nullable();
            $table->enum('status', array_column(FeeAgreementStatus::cases(), 'value'))->default(FeeAgreementStatus::Active->value);
            $table->date('signed_on')->nullable();
            // What the claim paid out or the estate came to, once it is finalised.
            $table->unsignedBigInteger('amount_received_cents')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['matter_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_agreements');
    }
};
