<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('flow_ref')->unique();
            $table->json('mail_recipients')->nullable();
            $table->string('slack_webhook_url')->nullable();
            $table->string('teams_workflow_webhook_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_notification_settings');
    }
};
