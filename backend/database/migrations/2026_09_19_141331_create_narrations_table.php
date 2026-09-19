<?php

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
        Schema::create('narrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('matter_id')->constrained('matters')->onDelete('cascade');
            $table->foreignId('activity_type_id')->constrained('activity_types');
            $table->foreignUuid('author_id')->constrained('users');
            $table->text('body');
            $table->decimal('quantity');
            $table->foreignId('court_id')->nullable()->constrained('courts');

            $table->dateTime('occurred_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('narrations');
    }
};
