<?php

declare(strict_types=1);

use App\Enums\Narration\ActivityMeasure;
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
        Schema::create('activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('measure', array_column(ActivityMeasure::cases(), 'value'));
            $table->integer('increment')->default(1);
            $table->boolean('default_billable')->default(false);
            $table->boolean('requires_court')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_types');
    }
};
