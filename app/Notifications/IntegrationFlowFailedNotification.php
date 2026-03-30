<?php

namespace App\Notifications;

use App\Models\FlowExecution;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

class IntegrationFlowFailedNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        public FlowExecution $execution,
        public string $errorMessage,
        public ?string $failedStepClass,
        public array $channels = ['mail', 'slack'],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Integration flow failed')
            ->line("Flow execution #{$this->execution->id} failed.")
            ->line("Reference: {$this->execution->flow_ref}")
            ->line('Error: '.$this->errorMessage)
            ->when($this->failedStepClass !== null, fn (MailMessage $m) => $m->line("Step: {$this->failedStepClass}"));
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $lines = [
            '*Integration flow failed*',
            "Execution ID: {$this->execution->id}",
            "Flow ref: {$this->execution->flow_ref}",
            'Error: '.$this->errorMessage,
        ];

        if ($this->failedStepClass !== null) {
            $lines[] = "Step: {$this->failedStepClass}";
        }

        return (new SlackMessage)->text(implode("\n", $lines));
    }
}
