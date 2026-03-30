<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\IntegrationFlowsPage;
use App\Integrations\FlowDefinitionRegistry;
use App\Models\FlowExecution;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IntegrationsOverviewWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -20;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Integrations'))
            ->modelLabel(__('Integration'))
            ->pluralModelLabel(__('Integrations'))
            ->records(fn (): array => $this->buildRecords())
            ->columns([
                Stack::make([
                    ImageColumn::make('image_display_url')
                        ->label('')
                        ->circular()
                        ->imageHeight(72)
                        ->imageWidth(72)
                        ->alignment(Alignment::Center)
                        ->defaultImageUrl(fn (array $record): string => $this->defaultAvatarUrl($record['name'])),
                    TextColumn::make('name')
                        ->weight(FontWeight::Bold)
                        ->alignment(Alignment::Center)
                        ->color('primary')
                        ->url(fn (array $record): string => IntegrationFlowsPage::getUrl([
                            'integrationSlug' => $record['slug'],
                        ])),
                    TextColumn::make('groups_overview')
                        ->label(__('Groups & flows'))
                        ->alignment(Alignment::Center)
                        ->wrap()
                        ->getStateUsing(fn (array $record): string => $record['groups_overview']),
                    TextColumn::make('last_run_at')
                        ->label(__('Last run'))
                        ->alignment(Alignment::Center)
                        ->formatStateUsing(function (?string $state): string {
                            if ($state === null || $state === '') {
                                return __('Never');
                            }

                            return Carbon::parse($state)->timezone(config('app.timezone'))->diffForHumans();
                        }),
                    TextColumn::make('failed_runs_hint')
                        ->label('')
                        ->alignment(Alignment::Center)
                        ->getStateUsing(fn (array $record): ?string => ($record['failed_count'] ?? 0) > 0
                            ? trans_choice(
                                '{1} :count failed run|[2,*] :count failed runs',
                                (int) $record['failed_count'],
                                ['count' => (int) $record['failed_count']],
                            )
                            : null)
                        ->badge()
                        ->color('danger'),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->paginated(false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRecords(): array
    {
        /** @var FlowDefinitionRegistry $registry */
        $registry = app(FlowDefinitionRegistry::class);
        $integrations = $registry->allIntegrations();

        if ($integrations === []) {
            return [];
        }

        $keys = array_values(array_unique(array_column($integrations, 'key')));

        $lastRuns = FlowExecution::query()
            ->whereNull('parent_flow_execution_id')
            ->whereIn('integration_key', $keys)
            ->select('integration_key', DB::raw('MAX(COALESCE(finished_at, started_at)) as last_at'))
            ->groupBy('integration_key')
            ->pluck('last_at', 'integration_key');

        $failures = FlowExecution::query()
            ->whereNull('parent_flow_execution_id')
            ->whereIn('integration_key', $keys)
            ->where('status', FlowExecution::STATUS_FAILED)
            ->selectRaw('integration_key, COUNT(*) as c')
            ->groupBy('integration_key')
            ->pluck('c', 'integration_key');

        return collect($integrations)
            ->map(function (array $i) use ($registry, $lastRuns, $failures): array {
                $key = $i['key'];
                $last = $lastRuns[$key] ?? null;
                $structure = $registry->integrationGroupsWithFlowCounts($i['slug']);

                return [
                    '__key' => $i['slug'],
                    'key' => $key,
                    'slug' => $i['slug'],
                    'name' => $i['name'],
                    'image_display_url' => $this->resolveImageUrl($i['image_url'] ?? null),
                    'groups_overview' => $this->formatGroupsOverview($structure['groups'], $structure['flow_count']),
                    'last_run_at' => $last !== null ? (string) $last : null,
                    'failed_count' => (int) ($failures[$key] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * @param  list<array{slug: string, flow_count: int}>  $groups
     */
    private function formatGroupsOverview(array $groups, int $totalFlows): string
    {
        if ($groups === []) {
            return __('No groups defined.');
        }

        $lines = [];

        foreach ($groups as $g) {
            $n = $g['flow_count'];
            $lines[] = $g['slug'].': '.trans_choice(
                '{0} 0 flows|{1} :count flow|[2,*] :count flows',
                $n,
                ['count' => $n],
            );
        }

        $lines[] = __('Total flows: :count', ['count' => $totalFlows]);

        return implode("\n", $lines);
    }

    private function resolveImageUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return url($url);
    }

    private function defaultAvatarUrl(string $name): string
    {
        return 'https://ui-avatars.com/api/?name='.rawurlencode($name).'&size=128&background=fef3c7&color=92400e';
    }
}
