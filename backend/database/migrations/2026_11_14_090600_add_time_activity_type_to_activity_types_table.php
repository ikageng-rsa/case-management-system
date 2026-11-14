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
        Schema::table('activity_types', function (Blueprint $table) {
            // Work counted in pages or kilometres can also charge the time it took,
            // at the rate of a kind of work counted in minutes, e.g. travelling time.
            $table->foreignId('time_activity_type_id')->nullable()->after('requires_court')
                ->constrained('activity_types')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('time_activity_type_id');
        });
    }
};
