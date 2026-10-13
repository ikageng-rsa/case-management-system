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
        // Users are identified by UUIDs, but the notifications table was made for integer ids.
        // SQLite does not mind; MySQL and Postgres would refuse the first notification.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('notifications', function (Blueprint $table) {
                $table->uuid('notifiable_id')->change();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            // On for everyone until they turn it off themselves.
            $table->boolean('sign_in_code_enabled')->default(true)->after('preferences');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sign_in_code_enabled');
        });
    }
};
