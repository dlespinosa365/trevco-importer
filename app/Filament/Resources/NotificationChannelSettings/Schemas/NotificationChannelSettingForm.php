<?php

namespace App\Filament\Resources\NotificationChannelSettings\Schemas;

use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NotificationChannelSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Email')
                    ->description('Addresses that receive failure notifications when an integration does not override them.')
                    ->schema([
                        TagsInput::make('mail_recipients')
                            ->label('Mail recipients')
                            ->placeholder('Add an email address')
                            ->nestedRecursiveRules(['email:filter'])
                            ->helperText('Used as a fallback when integration config leaves mail empty.'),
                    ]),
                Section::make('Slack')
                    ->schema([
                        TextInput::make('slack_webhook_url')
                            ->label('Incoming webhook URL')
                            ->url()
                            ->maxLength(2048)
                            ->helperText('Optional. Fallback when integration config has no Slack URL.'),
                    ]),
                Section::make('Microsoft Teams')
                    ->schema([
                        TextInput::make('teams_workflow_webhook_url')
                            ->label('Workflow webhook URL')
                            ->url()
                            ->maxLength(2048)
                            ->helperText('Optional. Use a Teams workflow "When a webhook request is received" URL. Fallback when integration config has no Teams URL.'),
                    ]),
            ]);
    }
}
