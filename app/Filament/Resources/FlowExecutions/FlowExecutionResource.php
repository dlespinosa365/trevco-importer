<?php

namespace App\Filament\Resources\FlowExecutions;

use App\Filament\Resources\FlowExecutions\Pages\ListFlowExecutions;
use App\Filament\Resources\FlowExecutions\Pages\ViewFlowExecution;
use App\Filament\Resources\FlowExecutions\RelationManagers\StepExecutionsRelationManager;
use App\Integrations\FlowDefinitionRegistry;
use App\Models\FlowExecution;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FlowExecutionResource extends Resource
{
    protected static ?string $model = FlowExecution::class;

    protected static ?string $navigationLabel = 'Flow runs';

    protected static ?string $modelLabel = 'flow run';

    protected static ?string $pluralModelLabel = 'flow runs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Integrations';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Overview'))
                    ->schema([
                        TextEntry::make('id'),
                        TextEntry::make('flow_ref'),
                        TextEntry::make('integration_key'),
                        TextEntry::make('parent_flow_execution_id')
                            ->label(__('Parent run'))
                            ->url(fn (?int $state): ?string => $state !== null
                                ? static::getUrl('view', ['record' => $state])
                                : null)
                            ->placeholder('—'),
                        TextEntry::make('fan_out_item_reference')
                            ->label(__('Fan-out item ref'))
                            ->placeholder('—'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (FlowExecution $record): string => $record->statusUiColor())
                            ->formatStateUsing(fn (FlowExecution $record): string => $record->statusUiLabel()),
                        TextEntry::make('triggered_by_type'),
                        TextEntry::make('triggeredByUser.name')
                            ->label(__('User'))
                            ->placeholder('—'),
                        TextEntry::make('started_at')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('finished_at')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('error_message')
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make(__('Payload & context'))
                    ->schema([
                        TextEntry::make('trigger_payload')
                            ->columnSpanFull()
                            ->formatStateUsing(fn (mixed $state): string => static::formatJsonState($state)),
                        TextEntry::make('context')
                            ->columnSpanFull()
                            ->formatStateUsing(fn (mixed $state): string => static::formatJsonState($state)),
                    ]),
            ]);
    }

    protected static function formatJsonState(mixed $state): string
    {
        if ($state === null || $state === '' || $state === []) {
            return '—';
        }

        if (is_string($state)) {
            $decoded = json_decode($state, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $state = $decoded;
            } else {
                return $state;
            }
        }

        if (! is_array($state)) {
            return (string) $state;
        }

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return is_string($json) ? $json : '—';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('3s')
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('flow_ref')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('integration_key')
                    ->label(__('Integration'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (FlowExecution $record): string => $record->statusUiColor())
                    ->formatStateUsing(fn (FlowExecution $record): string => $record->statusUiLabel())
                    ->sortable(),
                TextColumn::make('triggered_by_type')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('triggeredByUser.name')
                    ->label(__('User'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('error_message')
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                SelectFilter::make('integration_key')
                    ->label(__('Integration'))
                    ->options(function (): array {
                        $registry = app(FlowDefinitionRegistry::class);

                        return collect($registry->allIntegrations())
                            ->mapWithKeys(fn (array $i): array => [$i['key'] => $i['name']])
                            ->all();
                    })
                    ->searchable(),
                SelectFilter::make('flow_ref')
                    ->label(__('Flow'))
                    ->options(function (): array {
                        /** @var list<string> $refs */
                        $refs = app(FlowDefinitionRegistry::class)->allFlowRefs();

                        return collect($refs)
                            ->mapWithKeys(fn (string $ref): array => [$ref => $ref])
                            ->all();
                    })
                    ->searchable(),
                SelectFilter::make('status')
                    ->options([
                        FlowExecution::STATUS_PENDING => 'In queue',
                        FlowExecution::STATUS_RUNNING => 'Processing',
                        FlowExecution::STATUS_COMPLETED => 'Finished',
                        FlowExecution::STATUS_PARTIAL_COMPLETED => 'Error',
                        FlowExecution::STATUS_FAILED => 'Error',
                    ]),
                SelectFilter::make('has_error')
                    ->label(__('Has error'))
                    ->options([
                        '1' => __('Yes'),
                        '0' => __('No'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            '1' => $query->where(function (Builder $q): void {
                                $q->whereNotNull('error_message')
                                    ->orWhereIn('status', [
                                        FlowExecution::STATUS_FAILED,
                                        FlowExecution::STATUS_PARTIAL_COMPLETED,
                                    ]);
                            }),
                            '0' => $query->whereNull('error_message')
                                ->whereNotIn('status', [
                                    FlowExecution::STATUS_FAILED,
                                    FlowExecution::STATUS_PARTIAL_COMPLETED,
                                ]),
                            default => $query,
                        };
                    }),
                Filter::make('started_at_range')
                    ->label(__('Run date'))
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('started_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('started_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            StepExecutionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlowExecutions::route('/'),
            'view' => ViewFlowExecution::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNull('parent_flow_execution_id');
    }
}
