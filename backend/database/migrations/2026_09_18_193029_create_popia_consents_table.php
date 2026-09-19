<?php

use App\Enums\Client\PopiaConsentMethod;
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
        Schema::create('popia_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('client_id')->constrained('clients')->onDelete('cascade');
            $table->boolean('granted')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->enum('method', array_column(PopiaConsentMethod::cases(), 'value'));

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('popia_consents');
    }
};
