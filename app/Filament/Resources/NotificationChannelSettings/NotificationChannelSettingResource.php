<?php

namespace App\Filament\Resources\NotificationChannelSettings;

use App\Filament\Resources\NotificationChannelSettings\Pages\ManageNotificationChannelSetting;
use App\Filament\Resources\NotificationChannelSettings\Schemas\NotificationChannelSettingForm;
use App\Models\NotificationChannelSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class NotificationChannelSettingResource extends Resource
{
    protected static ?string $model = NotificationChannelSetting::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $modelLabel = 'notification channel settings';

    protected static ?string $pluralModelLabel = 'notification channel settings';

    public static function form(Schema $schema): Schema
    {
        return NotificationChannelSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'manage' => ManageNotificationChannelSetting::route('/{record}'),
        ];
    }

    /**
     * @param  array<mixed>  $parameters
     */
    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        $recordKey = $parameters['record'] ?? NotificationChannelSetting::current()->getKey();

        unset($parameters['record']);

        return static::getUrl('manage', [
            ...$parameters,
            'record' => $recordKey,
        ], $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return 'Notification channels';
    }
}
