<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channel_settings', function (Blueprint $table) {
            $table->id();
            $table->json('mail_recipients');
            $table->text('slack_webhook_url')->nullable();
            $table->text('teams_workflow_webhook_url')->nullable();
            $table->timestamps();
        });

        DB::table('notification_channel_settings')->insert([
            'id' => 1,
            'mail_recipients' => json_encode([]),
            'slack_webhook_url' => null,
            'teams_workflow_webhook_url' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_channel_settings');
    }
};
