<?php

declare(strict_types=1);

use App\Enums\Billing\FirmAccount;
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
        // Money paid out on a matter for the client, such as a sheriff's fee or
        // an expert's report, to be recovered on a bill.
        Schema::create('disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('matter_id')->constrained('matters')->cascadeOnDelete();
            $table->date('incurred_on');
            $table->string('description');
            $table->unsignedBigInteger('amount_cents');
            $table->enum('paid_from', array_column(FirmAccount::cases(), 'value'));
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['matter_id', 'incurred_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disbursements');
    }
};
