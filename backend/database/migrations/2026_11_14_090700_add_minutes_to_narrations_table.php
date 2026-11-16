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
        Schema::table('narrations', function (Blueprint $table) {
            // The time taken, for work whose kind charges time on top of its own measure.
            $table->unsignedInteger('minutes')->nullable()->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('narrations', function (Blueprint $table) {
            $table->dropColumn('minutes');
        });
    }
};
