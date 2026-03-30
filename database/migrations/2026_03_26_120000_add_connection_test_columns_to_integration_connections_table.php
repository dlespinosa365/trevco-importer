<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_connections', function (Blueprint $table) {
            $table->timestamp('last_connection_test_at')->nullable()->after('credentials');
            $table->boolean('last_connection_test_success')->nullable()->after('last_connection_test_at');
            $table->text('last_connection_test_error')->nullable()->after('last_connection_test_success');
        });
    }

    public function down(): void
    {
        Schema::table('integration_connections', function (Blueprint $table) {
            $table->dropColumn([
                'last_connection_test_at',
                'last_connection_test_success',
                'last_connection_test_error',
            ]);
        });
    }
};
