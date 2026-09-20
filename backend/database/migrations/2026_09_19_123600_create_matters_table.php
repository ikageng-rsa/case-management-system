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
        Schema::create('matters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->foreignUuid('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('matter_type_id')->constrained('matter_types');
            $table->integer('sequence_number');
            $table->smallInteger('opened_year');
            $table->string('title')->nullable();
            $table->foreignId('court_id')->nullable()->constrained('courts');

            $table->timestamp('instructed_at')->nullable();
            $table->timestamp('prescribes_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['matter_type_id', 'opened_year', 'sequence_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matters');
    }
};
