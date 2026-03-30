<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('step_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_execution_id')->constrained('flow_executions')->cascadeOnDelete();
            $table->unsignedBigInteger('flow_step_id')->nullable();
            $table->string('step_class');
            $table->unsignedInteger('step_index');
            $table->string('step_type');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->json('logs')->nullable();
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['flow_execution_id', 'step_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_executions');
    }
};
