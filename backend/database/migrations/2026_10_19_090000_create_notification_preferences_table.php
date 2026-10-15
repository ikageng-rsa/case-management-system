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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');

            // The kind of notification, as named by App\Enums\Notification\NotificationType.
            $table->string('type');

            // Where this person gets it. A row exists only once they have chosen.
            $table->boolean('database');
            $table->boolean('mail');

            // For the kinds that ask "how early?": the person's own default, in minutes.
            $table->unsignedInteger('lead_minutes')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
