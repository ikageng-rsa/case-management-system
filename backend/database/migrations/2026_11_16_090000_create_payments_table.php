<?php

declare(strict_types=1);

use App\Enums\Billing\FirmAccount;
use App\Enums\Billing\PaymentMethod;
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
        // Money received on an issued bill, with the proof of payment kept as a file.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // A payment is part of the firm's books, so a bill with payments is not removed with them.
            $table->foreignUuid('bill_id')->constrained('bills')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->date('received_on');
            $table->enum('method', array_column(PaymentMethod::cases(), 'value'));
            $table->enum('account', array_column(FirmAccount::cases(), 'value'));
            // The reference on the bank statement or slip, when there is one.
            $table->string('reference')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            // One recorded in error is reversed, not deleted: it stays on record and stops counting.
            $table->foreignUuid('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reverse_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['bill_id', 'received_on']);
        });

        Schema::table('bills', function (Blueprint $table) {
            // What the payments that still count come to, kept beside the total so the balance is read without adding them up.
            $table->unsignedBigInteger('paid_cents')->default(0)->after('total_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('paid_cents');
        });

        Schema::dropIfExists('payments');
    }
};
