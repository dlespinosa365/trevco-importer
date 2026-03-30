<?php

namespace App\Filament\Pages;

use App\Filament\Actions\FlowNotificationChannelsTableAction;
use App\Filament\Resources\FlowExecutions\FlowExecutionResource;
use App\Filament\Support\FlowListingRowBuilder;
use App\Integrations\FlowDefinitionRegistry;
use App\Jobs\ExecuteIntegrationFlowJob;
use App\Models\FlowExecution;
use App\Models\FlowSchedule;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Throwable;

class IntegrationFlowsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Integration flows';

    protected static ?string $slug = 'integrations/{integrationSlug}/flows';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.integration-flows-page';

    public string $integrationSlug;

    public string $integrationKey;

    public string $integrationName;

    public function mount(string $integrationSlug): void
    {
        $registry = app(FlowDefinitionRegistry::class);
        $integration = collect($registry->allIntegrations())
            ->first(fn (array $row): bool => $row['slug'] === $integrationSlug);

        if (! is_array($integration)) {
            throw new ModelNotFoundException('Integration not found.');
        }

        $this->integrationSlug = $integrationSlug;
        $this->integrationKey = $integration['key'];
        $this->integrationName = $integration['name'];
    }

    public function getTitle(): string
    {
        return $this->integrationName.' - flows';
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Flows')
            ->records(fn (): array => $this->buildFlowRows())
            ->poll('3s')
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->columns([
                TextColumn::make('flow_slug')
                    ->label('Flow')
                    ->searchable(),
                TextColumn::make('group_slug')
                    ->label('Group')
                    ->badge(),
                TextColumn::make('schedule_summary')
                    ->label('Schedule')
                    ->wrap()
                    ->formatStateUsing(fn (?string $state): string => $state ?? 'Not configured'),
                TextColumn::make('schedule_next_run')
                    ->label('Next run')
                    ->formatStateUsing(fn (?string $state): string => $state !== null
                        ? Carbon::parse($state)->timezone(config('app.timezone'))->diffForHumans()
                        : 'Not scheduled'),
                TextColumn::make('last_run_at')
                    ->label('Last run')
                    ->formatStateUsing(fn (?string $state): string => $state !== null
                        ? Carbon::parse($state)->timezone(config('app.timezone'))->diffForHumans()
                        : 'Never'),
                TextColumn::make('execution_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        FlowExecution::STATUS_PENDING => 'gray',
                        FlowExecution::STATUS_RUNNING => 'warning',
                        FlowExecution::STATUS_COMPLETED => 'success',
                        FlowExecution::STATUS_FAILED, FlowExecution::STATUS_PARTIAL_COMPLETED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        FlowExecution::STATUS_PENDING => 'in queue',
                        FlowExecution::STATUS_RUNNING => 'processing',
                        FlowExecution::STATUS_COMPLETED => 'finished',
                        FlowExecution::STATUS_FAILED, FlowExecution::STATUS_PARTIAL_COMPLETED => 'error',
                        default => 'unknown',
                    }),
                IconColumn::make('has_error')
                    ->label('Error')
                    ->boolean(),
                TextColumn::make('last_error')
                    ->label('Last error')
                    ->limit(100)
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('group_slug')
                    ->label('Group')
                    ->form([
                        ToggleButtons::make('value')
                            ->label('Group')
                            ->inline()
                            ->default('all')
                            ->options(function (): array {
                                $options = ['all' => 'All'];
                                foreach ($this->availableGroupSlugs() as $groupSlug) {
                                    $options[$groupSlug] = $groupSlug;
                                }

                                return $options;
                            }),
                    ]),
            ])
            ->actions([
                FlowNotificationChannelsTableAction::make(),
                Action::make('changeSchedule')
                    ->label('Change schedule')
                    ->icon('heroicon-o-clock')
                    ->fillForm(function (array $record): array {
                        $schedule = FlowSchedule::query()
                            ->where('flow_ref', $record['flow_ref'])
                            ->first();

                        return [
                            'is_active' => $schedule?->is_active ?? true,
                            'schedule_type' => $schedule?->schedule_type ?? FlowSchedule::TYPE_EVERY_MINUTES,
                            'every_minutes' => $schedule?->every_minutes ?? 15,
                            'daily_at' => $schedule?->daily_at,
                            'cron_expression' => $schedule?->cron_expression,
                            'timezone' => $schedule?->timezone ?? config('app.timezone'),
                        ];
                    })
                    ->form([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Select::make('schedule_type')
                            ->label('Type')
                            ->options([
                                FlowSchedule::TYPE_EVERY_MINUTES => 'Every N minutes',
                                FlowSchedule::TYPE_HOURLY => 'Hourly',
                                FlowSchedule::TYPE_DAILY => 'Daily',
                                FlowSchedule::TYPE_CRON => 'Cron expression',
                            ])
                            ->live()
                            ->afterStateUpdated(function (callable $set, ?string $state): void {
                                if ($state !== FlowSchedule::TYPE_EVERY_MINUTES) {
                                    $set('every_minutes', null);
                                }
                                if ($state !== FlowSchedule::TYPE_DAILY) {
                                    $set('daily_at', null);
                                }
                                if ($state !== FlowSchedule::TYPE_CRON) {
                                    $set('cron_expression', null);
                                }
                            })
                            ->required()
                            ->default(FlowSchedule::TYPE_EVERY_MINUTES),
                        TextInput::make('every_minutes')
                            ->numeric()
                            ->minValue(1)
                            ->required(fn (callable $get): bool => $get('schedule_type') === FlowSchedule::TYPE_EVERY_MINUTES)
                            ->visible(fn (callable $get): bool => $get('schedule_type') === FlowSchedule::TYPE_EVERY_MINUTES),
                        TimePicker::make('daily_at')
                            ->seconds(false)
                            ->required(fn (callable $get): bool => $get('schedule_type') === FlowSchedule::TYPE_DAILY)
                            ->visible(fn (callable $get): bool => $get('schedule_type') === FlowSchedule::TYPE_DAILY),
                        TextInput::make('cron_expression')
                            ->placeholder('* * * * *')
                            ->required(fn (callable $get): bool => $get('schedule_type') === FlowSchedule::TYPE_CRON)
                            ->visible(fn (callable $get): bool => $get('schedule_type') === FlowSchedule::TYPE_CRON),
                        TextInput::make('timezone')
                            ->required()
                            ->default(config('app.timezone')),
                        Placeholder::make('schedule_preview')
                            ->label('Schedule preview')
                            ->content(function (callable $get): string {
                                $scheduleType = (string) ($get('schedule_type') ?? FlowSchedule::TYPE_EVERY_MINUTES);
                                $timezone = (string) ($get('timezone') ?? config('app.timezone'));
                                $everyMinutes = is_numeric($get('every_minutes')) ? (int) $get('every_minutes') : null;
                                $dailyAt = $get('daily_at');
                                $cronExpression = $get('cron_expression');

                                $humanized = match ($scheduleType) {
                                    FlowSchedule::TYPE_EVERY_MINUTES => 'Every '.max(1, (int) ($everyMinutes ?? 1)).' minute(s)',
                                    FlowSchedule::TYPE_HOURLY => 'Hourly (at minute 00)',
                                    FlowSchedule::TYPE_DAILY => 'Daily at '.(is_string($dailyAt) && $dailyAt !== '' ? $dailyAt : '--:--'),
                                    FlowSchedule::TYPE_CRON => 'Cron: '.(is_string($cronExpression) && $cronExpression !== '' ? $cronExpression : '(missing)'),
                                    default => 'Unknown schedule type',
                                };

                                try {
                                    $preview = new FlowSchedule([
                                        'flow_ref' => 'preview',
                                        'is_active' => true,
                                        'timezone' => $timezone !== '' ? $timezone : (string) config('app.timezone'),
                                        'schedule_type' => $scheduleType,
                                        'every_minutes' => $everyMinutes,
                                        'daily_at' => is_string($dailyAt) ? $dailyAt : null,
                                        'cron_expression' => is_string($cronExpression) ? $cronExpression : null,
                                    ]);

                                    $nextRun = $preview->calculateNextRunAt();
                                    $nextRunLabel = $nextRun
                                        ->timezone(config('app.timezone'))
                                        ->toDayDateTimeString();
                                } catch (Throwable $e) {
                                    $nextRunLabel = 'Unable to compute next run (check schedule values).';
                                }

                                return 'Configured: '.$humanized."\n".'Next run: '.$nextRunLabel;
                            }),
                    ])
                    ->action(function (array $data, array $record): void {
                        $schedule = FlowSchedule::query()->firstOrNew(['flow_ref' => $record['flow_ref']]);
                        $schedule->forceFill([
                            'is_active' => (bool) ($data['is_active'] ?? true),
                            'schedule_type' => (string) $data['schedule_type'],
                            'every_minutes' => $data['schedule_type'] === FlowSchedule::TYPE_EVERY_MINUTES
                                ? max(1, (int) ($data['every_minutes'] ?? 1))
                                : null,
                            'daily_at' => $data['schedule_type'] === FlowSchedule::TYPE_DAILY
                                ? ($data['daily_at'] ?? null)
                                : null,
                            'cron_expression' => $data['schedule_type'] === FlowSchedule::TYPE_CRON
                                ? ($data['cron_expression'] ?? null)
                                : null,
                            'timezone' => (string) ($data['timezone'] ?? config('app.timezone')),
                        ]);

                        $schedule->next_run_at = $schedule->calculateNextRunAt();
                        $schedule->save();

                        Notification::make()
                            ->title('Schedule updated')
                            ->success()
                            ->send();
                    }),
                Action::make('viewRuns')
                    ->label('View runs')
                    ->icon('heroicon-o-list-bullet')
                    ->url(fn (array $record): string => FlowExecutionResource::getUrl(parameters: [
                        'filters' => [
                            'flow_ref' => [
                                'value' => $record['flow_ref'],
                            ],
                        ],
                    ])),
                Action::make('viewErrorLogs')
                    ->label('Error logs')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->url(fn (array $record): string => FlowErrorLogsPage::getUrl([
                        'integrationSlug' => $this->integrationSlug,
                        'flow_ref' => $record['flow_ref'],
                    ])),
                Action::make('runNow')
                    ->label('Run now')
                    ->icon('heroicon-o-play')
                    ->requiresConfirmation()
                    ->action(function (array $record): void {
                        $triggeredByUserId = auth()->user()?->getAuthIdentifier();

                        ExecuteIntegrationFlowJob::dispatchQueued(
                            flowRef: $record['flow_ref'],
                            triggeredByUserId: is_numeric($triggeredByUserId) ? (int) $triggeredByUserId : null,
                            triggeredByType: 'manual',
                            triggerPayload: [
                                'source' => 'filament_integration_flows_page',
                            ],
                        );

                        Notification::make()
                            ->title('Flow dispatched')
                            ->body('The flow was queued for execution.')
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated(false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildFlowRows(): array
    {
        return $this->flowListingRowBuilder()->buildRows($this->integrationSlug, $this->currentGroupFilter());
    }

    private function flowListingRowBuilder(): FlowListingRowBuilder
    {
        return app(FlowListingRowBuilder::class);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to dashboard')
                ->url(Dashboard::getUrl())
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    private function currentGroupFilter(): string
    {
        $value = data_get($this->tableFilters ?? [], 'group_slug.value', 'all');

        return is_string($value) && $value !== '' ? $value : 'all';
    }

    /**
     * @return list<string>
     */
    private function availableGroupSlugs(): array
    {
        return $this->flowListingRowBuilder()->groupSlugsForIntegration($this->integrationSlug);
    }
}
