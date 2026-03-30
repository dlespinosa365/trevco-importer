<?php

namespace App\Filament\Resources\NotificationChannelSettings\Pages;

use App\Filament\Resources\NotificationChannelSettings\NotificationChannelSettingResource;
use App\Models\NotificationChannelSetting;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class ManageNotificationChannelSetting extends EditRecord
{
    protected static string $resource = NotificationChannelSettingResource::class;

    public function mount(int|string $record): void
    {
        parent::mount(NotificationChannelSetting::current()->getKey());
    }

    public function getTitle(): string|Htmlable
    {
        return 'Notification channel settings';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $recipients = $data['mail_recipients'] ?? [];
        if (is_array($recipients)) {
            $data['mail_recipients'] = array_values(array_filter(
                array_map(fn ($v) => is_string($v) ? trim($v) : '', $recipients),
                fn (string $v): bool => $v !== ''
            ));
        }

        foreach (['slack_webhook_url', 'teams_workflow_webhook_url'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }
}
