<?php

declare(strict_types=1);

use App\Enums\Client\ContactKind;
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
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('client_id')->constrained('clients')->onDelete('cascade');
            $table->enum('kind', array_column(ContactKind::cases(), 'value'));
            $table->text('value');
            $table->string('value_hash', 64)->index();
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->unique(['client_id', 'kind', 'value_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
    }
};
