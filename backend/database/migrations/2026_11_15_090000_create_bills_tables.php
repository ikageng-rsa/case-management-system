<?php

declare(strict_types=1);

use App\Enums\Billing\BillKind;
use App\Enums\Billing\BillLineKind;
use App\Enums\Billing\BillStatus;
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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            // A bill is part of the firm's books, so a matter with bills is not removed with them.
            $table->foreignUuid('matter_id')->constrained('matters')->restrictOnDelete();
            $table->enum('kind', array_column(BillKind::cases(), 'value'));
            $table->enum('status', array_column(BillStatus::cases(), 'value'))->default(BillStatus::Draft->value);
            // Given when the bill is issued, e.g. INV/2026/0001.
            $table->string('number')->nullable()->unique();
            $table->string('addressee');
            $table->enum('scale', array_column(TariffScale::cases(), 'value'))->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            // The totals as drawn, so a bill reads the same whatever changes later.
            $table->unsignedBigInteger('fees_cents')->default(0);
            $table->unsignedInteger('drawing_fee_percent_hundredths')->default(0);
            $table->unsignedBigInteger('drawing_fee_cents')->default(0);
            $table->unsignedBigInteger('disbursements_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->foreignUuid('drafted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignUuid('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['matter_id', 'status']);
        });

        Schema::create('bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->enum('kind', array_column(BillLineKind::cases(), 'value'));
            $table->unsignedSmallInteger('position')->default(0);
            $table->date('occurred_on');
            $table->string('item')->nullable();
            $table->text('description');
            $table->decimal('units', 10, 2)->default(0);
            $table->unsignedBigInteger('rate_cents')->default(0);
            $table->unsignedBigInteger('fee_cents')->default(0);
            $table->unsignedBigInteger('disbursement_cents')->default(0);
            // Where the line came from, and whose time it is for time on top.
            $table->foreignUuid('narration_id')->nullable()->constrained('narrations')->nullOnDelete();
            $table->foreignId('disbursement_id')->nullable()->constrained('disbursements')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            // A changed amount keeps what it was and who changed it.
            $table->unsignedBigInteger('original_amount_cents')->nullable();
            $table->foreignUuid('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            /*
             * What the line claims, such as "statement:narration:<id>", while its
             * bill is not void. Unique, so the same work or cost is never on two
             * live bills of the same kind; cleared when the bill is voided.
             */
            $table->string('claim')->nullable()->unique();
            $table->timestamps();

            $table->index(['bill_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_lines');
        Schema::dropIfExists('bills');
    }
};
