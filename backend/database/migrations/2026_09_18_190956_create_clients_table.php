<?php

use App\Enums\Client\ClientType;
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
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', array_column(ClientType::cases(), 'value'));
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('entity_name')->nullable();
            $table->text('id_number')->nullable();
            $table->string('id_number_hash', 64)->nullable()->index();
            $table->string('registration_number')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
