<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores credentials per user and logical key (never put secrets under integrations/).
     * user_id null: optional shared row for system jobs; prefer scoping to a user when you run flows as a user.
     */
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('driver')->default('array');
            $table->text('credentials');
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connections');
    }
};
