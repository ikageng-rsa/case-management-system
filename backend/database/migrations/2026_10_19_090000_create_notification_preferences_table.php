<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

            $table->timestamps();

            $table->unique(['user_id', 'type']);
        });

        // What people had already chosen, kept: it used to live in one JSON column.
        DB::table('users')->whereNotNull('preferences')->orderBy('id')->each(function ($user): void {
            $chosen = json_decode((string) $user->preferences, true)['notifications'] ?? [];

            foreach ($chosen as $type => $channels) {
                DB::table('notification_preferences')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'type' => $type,
                    'database' => (bool) ($channels['database'] ?? false),
                    'mail' => (bool) ($channels['mail'] ?? false),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferences');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('preferences')->nullable();
        });

        Schema::dropIfExists('notification_preferences');
    }
};
