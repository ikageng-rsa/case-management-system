<?php

declare(strict_types=1);

use App\Enums\Matter\PartyRole;
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
        Schema::create('matter_parties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('matter_id')->constrained('matters')->onDelete('cascade');
            $table->string('name');
            $table->enum('role', array_column(PartyRole::cases(), 'value'));

            // Who acts for them, where someone does: their attorneys or counsel.
            $table->string('representative')->nullable();

            $table->timestamps();

            $table->index(['matter_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matter_parties');
    }
};
