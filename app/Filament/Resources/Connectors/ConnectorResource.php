<?php

namespace App\Filament\Resources\Connectors;

use App\Connectors\ConnectorConnectionTester;
use App\Enums\ConnectorType;
use App\Filament\Resources\Connectors\Pages\CreateConnector;
use App\Filament\Resources\Connectors\Pages\EditConnector;
use App\Filament\Resources\Connectors\Pages\ListConnectors;
use App\Filament\Resources\Connectors\Schemas\ConnectorForm;
use App\Models\Connector;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class ConnectorResource extends Resource
{
    protected static ?string $model = Connector::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ConnectorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('key')
                    ->label('Key')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('connector_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?ConnectorType $state): string => $state?->label() ?? '—'),
                IconColumn::make('last_connection_test_success')
                    ->label(__('Connection test'))
                    ->boolean()
                    ->trueIcon(new HtmlString('<img src="'.e(asset('images/connector-connection/ok.gif')).'" width="28" height="28" alt="" class="inline-block" loading="lazy" />'))
                    ->falseIcon(new HtmlString('<img src="'.e(asset('images/connector-connection/fail.gif')).'" width="28" height="28" alt="" class="inline-block" loading="lazy" />'))
                    ->emptyTooltip(__('Not tested yet'))
                    ->tooltip(function (?bool $state, Connector $record): ?string {
                        if ($record->last_connection_test_at === null) {
                            return null;
                        }
                        $when = $record->last_connection_test_at
                            ->timezone(config('app.timezone'))
                            ->toDayDateTimeString();
                        if ($state === true) {
                            return __('OK').' · '.$when;
                        }

                        return ($record->last_connection_test_error ?? __('Failed')).' · '.$when;
                    }),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('testConnection')
                    ->label(__('Test connection'))
                    ->icon(Heroicon::OutlinedSignal)
                    ->authorize('update')
                    ->action(function (Connector $record): void {
                        $result = app(ConnectorConnectionTester::class)->test($record);
                        if ($result->success) {
                            Notification::make()
                                ->title(__('Connection successful'))
                                ->success()
                                ->send();

                            return;
                        }
                        Notification::make()
                            ->title(__('Connection failed'))
                            ->danger()
                            ->body($result->message ?? '')
                            ->persistent()
                            ->send();
                    })
                    ->visible(fn (Connector $record): bool => ConnectorConnectionTester::supports($record->connector_type)),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConnectors::route('/'),
            'create' => CreateConnector::route('/create'),
            'edit' => EditConnector::route('/{record}/edit'),
        ];
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if ($record === null) {
            return null;
        }

        /** @var Connector $record */
        $name = $record->name;

        return is_string($name) && $name !== '' ? $name : $record->key;
    }
}
