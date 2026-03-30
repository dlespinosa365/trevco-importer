<?php

namespace App\Filament\Actions;

use App\Models\FlowNotificationSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification as FilamentNotification;

final class FlowNotificationChannelsTableAction
{
    public static function make(): Action
    {
        return Action::make('notificationChannels')
            ->label(__('Notification channels'))
            ->icon('heroicon-o-bell')
            ->modalHeading(__('Failure notification channels'))
            ->modalDescription(__('Turn channels on or off for this flow only. Recipients and webhook URLs come from the integration and global notification settings.'))
            ->fillForm(function (array $record): array {
                $setting = FlowNotificationSetting::query()
                    ->where('flow_ref', $record['flow_ref'])
                    ->first();

                return [
                    'mail_enabled' => $setting?->mail_enabled ?? true,
                    'slack_enabled' => $setting?->slack_enabled ?? true,
                    'teams_enabled' => $setting?->teams_enabled ?? true,
                ];
            })
            ->form([
                Placeholder::make('hint')
                    ->label('')
                    ->content(__('When all channels are enabled, this flow follows the same rules as integrations without per-flow settings.')),
                Toggle::make('mail_enabled')
                    ->label(__('Mail'))
                    ->helperText(__('Send failure emails when mail is configured globally or on the integration.'))
                    ->default(true),
                Toggle::make('slack_enabled')
                    ->label(__('Slack'))
                    ->helperText(__('Send to the Slack webhook from global or integration settings.'))
                    ->default(true),
                Toggle::make('teams_enabled')
                    ->label(__('Microsoft Teams'))
                    ->helperText(__('Send to the Teams workflow webhook from global or integration settings.'))
                    ->default(true),
            ])
            ->action(function (array $data, array $record): void {
                $mailEnabled = (bool) ($data['mail_enabled'] ?? true);
                $slackEnabled = (bool) ($data['slack_enabled'] ?? true);
                $teamsEnabled = (bool) ($data['teams_enabled'] ?? true);

                if ($mailEnabled && $slackEnabled && $teamsEnabled) {
                    FlowNotificationSetting::query()->where('flow_ref', $record['flow_ref'])->delete();

                    FilamentNotification::make()
                        ->title(__('Notification channels reset'))
                        ->body(__('This flow uses all configured channels (integration and global defaults).'))
                        ->success()
                        ->send();

                    return;
                }

                FlowNotificationSetting::query()->updateOrCreate(
                    ['flow_ref' => $record['flow_ref']],
                    [
                        'mail_enabled' => $mailEnabled,
                        'slack_enabled' => $slackEnabled,
                        'teams_enabled' => $teamsEnabled,
                    ],
                );

                FilamentNotification::make()
                    ->title(__('Notification channels saved'))
                    ->success()
                    ->send();
            });
    }
}
