<?php

use App\Enums\Matter\Assignment;
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
        Schema::create('matter_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('matter_id')->constrained('matters')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users');
            $table->enum('capacity', array_column(Assignment::cases(), 'value'));

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matter_assignments');
    }
};
