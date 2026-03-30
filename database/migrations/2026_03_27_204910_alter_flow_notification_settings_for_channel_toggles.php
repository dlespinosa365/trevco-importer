<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flow_notification_settings', function (Blueprint $table) {
            $table->dropColumn([
                'mail_recipients',
                'slack_webhook_url',
                'teams_workflow_webhook_url',
            ]);
        });

        Schema::table('flow_notification_settings', function (Blueprint $table) {
            $table->boolean('mail_enabled')->default(true);
            $table->boolean('slack_enabled')->default(true);
            $table->boolean('teams_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('flow_notification_settings', function (Blueprint $table) {
            $table->dropColumn([
                'mail_enabled',
                'slack_enabled',
                'teams_enabled',
            ]);
        });

        Schema::table('flow_notification_settings', function (Blueprint $table) {
            $table->json('mail_recipients')->nullable();
            $table->string('slack_webhook_url')->nullable();
            $table->string('teams_workflow_webhook_url')->nullable();
        });
    }
};
