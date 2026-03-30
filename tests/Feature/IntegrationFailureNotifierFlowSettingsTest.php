<?php

use App\Integrations\IntegrationFailureNotifier;
use App\Models\FlowExecution;
use App\Models\FlowNotificationSetting;
use App\Models\NotificationChannelSetting;
use App\Notifications\IntegrationFlowFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('sends failure mail using global recipients when the flow has no per-flow settings', function () {
    Notification::fake();

    $global = NotificationChannelSetting::query()->firstOrFail();
    $global->mail_recipients = ['global-ops@example.com'];
    $global->save();

    $flowRef = 'dummy-json-netsuite/orders-to-netsuite/sync-orders';

    $execution = FlowExecution::query()->create([
        'flow_ref' => $flowRef,
        'integration_key' => 'dummy-json-netsuite',
        'status' => FlowExecution::STATUS_FAILED,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => 'boom',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    app(IntegrationFailureNotifier::class)->notify(
        $execution->fresh(),
        new RuntimeException('boom'),
        'SomeStep',
    );

    Notification::assertSentOnDemand(IntegrationFlowFailedNotification::class);
});

it('does not send mail when mail is disabled for the flow', function () {
    Notification::fake();

    $global = NotificationChannelSetting::query()->firstOrFail();
    $global->mail_recipients = ['global-ops@example.com'];
    $global->save();

    $flowRef = 'dummy-json-netsuite/orders-to-netsuite/sync-orders';

    FlowNotificationSetting::query()->create([
        'flow_ref' => $flowRef,
        'mail_enabled' => false,
        'slack_enabled' => true,
        'teams_enabled' => true,
    ]);

    $execution = FlowExecution::query()->create([
        'flow_ref' => $flowRef,
        'integration_key' => 'dummy-json-netsuite',
        'status' => FlowExecution::STATUS_FAILED,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => 'boom',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    app(IntegrationFailureNotifier::class)->notify(
        $execution->fresh(),
        new RuntimeException('boom'),
        'SomeStep',
    );

    Notification::assertNothingSent();
});

it('does not notify any channel when all flow channel toggles are off', function () {
    Notification::fake();

    $global = NotificationChannelSetting::query()->firstOrFail();
    $global->mail_recipients = ['global-ops@example.com'];
    $global->slack_webhook_url = 'https://hooks.slack.com/services/TEST/TEST/TEST';
    $global->save();

    $flowRef = 'dummy-json-netsuite/orders-to-netsuite/sync-orders';

    FlowNotificationSetting::query()->create([
        'flow_ref' => $flowRef,
        'mail_enabled' => false,
        'slack_enabled' => false,
        'teams_enabled' => false,
    ]);

    $execution = FlowExecution::query()->create([
        'flow_ref' => $flowRef,
        'integration_key' => 'dummy-json-netsuite',
        'status' => FlowExecution::STATUS_FAILED,
        'triggered_by_user_id' => null,
        'triggered_by_type' => 'manual',
        'context' => [],
        'trigger_payload' => [],
        'error_message' => 'boom',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    app(IntegrationFailureNotifier::class)->notify(
        $execution->fresh(),
        new RuntimeException('boom'),
        'SomeStep',
    );

    Notification::assertNothingSent();
});
