<?php

namespace App\Integrations;

use App\Models\FlowExecution;
use App\Models\FlowNotificationSetting;
use App\Models\NotificationChannelSetting;
use App\Notifications\IntegrationFlowFailedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Osama\LaravelTeamsNotification\TeamsNotification;
use Throwable;

final class IntegrationFailureNotifier
{
    public function __construct(
        private FlowDefinitionRegistry $registry,
    ) {}

    public function notify(FlowExecution $execution, Throwable $exception, ?string $failedStepClass): void
    {
        if ($execution->flow_ref === '') {
            return;
        }

        try {
            $definition = $this->registry->resolve($execution->flow_ref);
        } catch (Throwable) {
            return;
        }

        $config = $definition->failureNotifications;
        $mailRaw = $config['mail'] ?? [];
        $mails = is_array($mailRaw) ? $mailRaw : [$mailRaw];
        $mails = array_values(array_filter(array_map(
            fn ($v) => is_string($v) && $v !== '' ? $v : null,
            $mails
        )));

        $slackUrl = $config['slack_webhook_url'] ?? null;
        $slackUrl = is_string($slackUrl) && $slackUrl !== '' ? $slackUrl : null;

        $teamsUrl = $config['teams_workflow_webhook_url'] ?? null;
        $teamsUrl = is_string($teamsUrl) && $teamsUrl !== '' ? $teamsUrl : null;

        $global = NotificationChannelSetting::query()->whereKey(1)->first();
        if ($global !== null) {
            if ($mails === []) {
                $fallback = $global->mail_recipients ?? [];
                $fallback = is_array($fallback) ? $fallback : [];
                $mails = array_values(array_filter(array_map(
                    fn ($v) => is_string($v) && $v !== '' ? $v : null,
                    $fallback
                )));
            }
            if ($slackUrl === null && filled($global->slack_webhook_url)) {
                $slackUrl = $global->slack_webhook_url;
            }
            if ($teamsUrl === null && filled($global->teams_workflow_webhook_url)) {
                $teamsUrl = $global->teams_workflow_webhook_url;
            }
        }

        [$mails, $slackUrl, $teamsUrl] = $this->applyFlowChannelToggles($execution->flow_ref, $mails, $slackUrl, $teamsUrl);

        if ($mails === [] && $slackUrl === null && $teamsUrl === null) {
            return;
        }

        $message = $exception->getMessage();
        $execution->refresh();

        foreach ($mails as $address) {
            Notification::route('mail', $address)->notifyNow(
                new IntegrationFlowFailedNotification($execution, $message, $failedStepClass, ['mail'])
            );
        }

        if ($slackUrl !== null) {
            Notification::route('slack', $slackUrl)->notifyNow(
                new IntegrationFlowFailedNotification($execution, $message, $failedStepClass, ['slack'])
            );
        }

        if ($teamsUrl !== null) {
            try {
                (new TeamsNotification($teamsUrl))
                    ->error()
                    ->sendMessage('Integration flow failed', [
                        'Execution ID' => (string) $execution->id,
                        'Flow ref' => $execution->flow_ref,
                        'Integration' => $execution->integration_key,
                        'Error' => $message,
                        'Step' => $failedStepClass ?? 'n/a',
                    ]);
            } catch (Throwable $e) {
                Log::warning('Teams failure notification could not be sent.', [
                    'execution_id' => $execution->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  list<string>  $mails
     * @return array{0: list<string>, 1: string|null, 2: string|null}
     */
    private function applyFlowChannelToggles(string $flowRef, array $mails, ?string $slackUrl, ?string $teamsUrl): array
    {
        $stored = FlowNotificationSetting::query()->where('flow_ref', $flowRef)->first();
        if ($stored === null) {
            return [$mails, $slackUrl, $teamsUrl];
        }

        if (! $stored->mail_enabled) {
            $mails = [];
        }

        if (! $stored->slack_enabled) {
            $slackUrl = null;
        }

        if (! $stored->teams_enabled) {
            $teamsUrl = null;
        }

        return [$mails, $slackUrl, $teamsUrl];
    }
}
