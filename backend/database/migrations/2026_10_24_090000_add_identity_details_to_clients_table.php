<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // What kind of document the number is (SA ID, passport, other), so each is checked and kept apart.
            $table->string('id_type')->nullable()->after('entity_name');

            // Encrypted at rest; for an individual, whatever country they are from.
            $table->text('date_of_birth')->nullable()->after('registration_number');
        });

        // Every number on file so far is a South African ID.
        DB::table('clients')->whereNotNull('id_number')->update(['id_type' => 'sa_id']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['id_type', 'date_of_birth']);
        });
    }
};
