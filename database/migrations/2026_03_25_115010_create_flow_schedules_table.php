<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('flow_ref');
            $table->boolean('is_active')->default(true)->index();
            $table->string('timezone')->default('UTC');
            $table->string('schedule_type');
            $table->unsignedSmallInteger('every_minutes')->nullable();
            $table->time('daily_at')->nullable();
            $table->string('cron_expression')->nullable();
            $table->foreignId('run_as_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('trigger_payload')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
            $table->index('flow_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_schedules');
    }
};
