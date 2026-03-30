<?php

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
        Schema::table('flow_executions', function (Blueprint $table) {
            $table->foreignId('parent_flow_execution_id')
                ->nullable()
                ->after('id')
                ->constrained('flow_executions')
                ->nullOnDelete();
            $table->string('fan_out_item_reference')->nullable()->after('integration_key');
            $table->timestamp('aggregated_into_parent_at')->nullable()->after('finished_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flow_executions', function (Blueprint $table) {
            $table->dropForeign(['parent_flow_execution_id']);
            $table->dropColumn([
                'parent_flow_execution_id',
                'fan_out_item_reference',
                'aggregated_into_parent_at',
            ]);
        });
    }
};
