<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Time on top was charged at the rate of another kind of work. The gazette
     * quotes no such rate, so the time is now rated when the bill is raised,
     * and a kind of work only says whether it records the time taken.
     */
    public function up(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->boolean('charges_time_on_top')->default(false)->after('requires_court');
        });

        DB::table('activity_types')->whereNotNull('time_activity_type_id')->update(['charges_time_on_top' => true]);

        Schema::table('activity_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('time_activity_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->foreignId('time_activity_type_id')->nullable()->after('requires_court')
                ->constrained('activity_types')->nullOnDelete();
        });

        Schema::table('activity_types', function (Blueprint $table) {
            $table->dropColumn('charges_time_on_top');
        });
    }
};
