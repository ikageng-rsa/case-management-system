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
        Schema::table('users', function (Blueprint $table) {
            // The staff record: kept by the firm's directors, never by the person it describes.
            $table->string('job_title')->nullable()->after('email');
            $table->text('mobile')->nullable()->after('job_title');
            $table->text('office_phone')->nullable()->after('mobile');
            $table->string('employee_number')->nullable()->unique()->after('office_phone');

            // What the person chooses for themselves, as a small set of named settings.
            $table->json('preferences')->nullable()->after('employee_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['employee_number']);
            $table->dropColumn(['job_title', 'mobile', 'office_phone', 'employee_number', 'preferences']);
        });
    }
};
