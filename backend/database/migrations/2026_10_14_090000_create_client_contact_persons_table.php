<?php

declare(strict_types=1);

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
        Schema::create('client_contact_persons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->onDelete('cascade');
            $table->string('name');
            $table->string('role')->nullable();

            // Encrypted at rest, like every other way of reaching a client.
            $table->text('mobile')->nullable();
            $table->text('email')->nullable();

            // The first person to call; the rest are alternatives.
            $table->boolean('is_primary')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_contact_persons');
    }
};
