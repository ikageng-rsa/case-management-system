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
        Schema::create('diary_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('matter_id')->constrained('matters')->onDelete('cascade');
            $table->foreignUuid('narration_id')->nullable()->constrained('narrations')->nullOnDelete();
            $table->text('body');
            $table->timestamp('due_at');
            $table->foreignUuid('assigned_to')->constrained('users');

            $table->timestamp('completed_at')->nullable();

            $table->timestamp('overdue_notified_at')->nullable();

            // How long before it falls due the assignee is reminded, and when they were. Empty is no reminder.
            $table->unsignedInteger('remind_before_minutes')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->foreignUuid('completed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diary_entries');
    }
};
