<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_connections', function (Blueprint $table) {
            $table->string('connector_type')->nullable()->after('key');
        });

        Schema::table('integration_connections', function (Blueprint $table) {
            $table->dropColumn('driver');
        });
    }

    public function down(): void
    {
        Schema::table('integration_connections', function (Blueprint $table) {
            $table->string('driver')->default('array');
        });

        Schema::table('integration_connections', function (Blueprint $table) {
            $table->dropColumn('connector_type');
        });
    }
};
