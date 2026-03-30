<?php

namespace App\Filament\Support;

use App\Integrations\FlowDefinitionRegistry;
use App\Models\FlowExecution;
use App\Models\FlowSchedule;
use Illuminate\Support\Collection;

final class FlowListingRowBuilder
{
    public function __construct(
        private readonly FlowDefinitionRegistry $registry,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function buildRows(string $integrationFilter, string $groupSlugFilter): array
    {
        $integrationFilter = $this->normalizeIntegrationFilter($integrationFilter);
        $groupSlugFilter = $this->normalizeGroupFilter($integrationFilter, $groupSlugFilter);

        $refs = collect($this->registry->allFlowRefs());

        if ($integrationFilter !== 'all') {
            $refs = $refs->filter(fn (string $ref): bool => str_starts_with($ref, $integrationFilter.'/'));
        }

        if ($groupSlugFilter !== 'all') {
            $refs = $refs->filter(function (string $ref) use ($groupSlugFilter): bool {
                [, $groupSlug] = explode('/', $ref);

                return $groupSlug === $groupSlugFilter;
            });
        }

        $refs = $refs->values();

        if ($refs->isEmpty()) {
            return [];
        }

        $namesBySlug = collect($this->registry->allIntegrations())
            ->mapWithKeys(fn (array $row): array => [$row['slug'] => $row['name']]);

        $executionsByRef = FlowExecution::query()
            ->whereNull('parent_flow_execution_id')
            ->whereIn('flow_ref', $refs->all())
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('flow_ref');

        $schedulesByRef = FlowSchedule::query()
            ->whereIn('flow_ref', $refs->all())
            ->get()
            ->keyBy('flow_ref');

        return $refs
            ->map(function (string $flowRef) use ($executionsByRef, $schedulesByRef, $namesBySlug): array {
                [$integrationSlug, $groupSlug, $flowSlug] = explode('/', $flowRef);
                /** @var Collection<int, FlowExecution> $runs */
                $runs = $executionsByRef->get($flowRef, collect());
                /** @var ?FlowExecution $display */
                $display = $runs->first(
                    fn (FlowExecution $run): bool => in_array($run->status, [
                        FlowExecution::STATUS_PENDING,
                        FlowExecution::STATUS_RUNNING,
                    ], true)
                ) ?? $runs->first();
                /** @var ?FlowSchedule $schedule */
                $schedule = $schedulesByRef->get($flowRef);

                return [
                    '__key' => $flowRef,
                    'flow_ref' => $flowRef,
                    'flow_slug' => $flowSlug,
                    'group_slug' => $groupSlug,
                    'integration_slug' => $integrationSlug,
                    'integration_name' => (string) ($namesBySlug->get($integrationSlug) ?? $integrationSlug),
                    'schedule_summary' => $this->humanizeSchedule($schedule),
                    'schedule_next_run' => $schedule?->next_run_at?->toDateTimeString(),
                    'last_run_at' => $display?->finished_at?->toDateTimeString() ?? $display?->started_at?->toDateTimeString(),
                    'execution_status' => $display?->status ?? FlowExecution::STATUS_PENDING,
                    'last_error' => $display?->error_message,
                    'has_error' => filled($display?->error_message) || in_array($display?->status, [
                        FlowExecution::STATUS_FAILED,
                        FlowExecution::STATUS_PARTIAL_COMPLETED,
                    ], true),
                ];
            })
            ->all();
    }

    /**
     * @return list<string>
     */
    public function groupSlugsForIntegration(string $integrationSlug): array
    {
        return collect($this->registry->allFlowRefs())
            ->filter(fn (string $ref): bool => str_starts_with($ref, $integrationSlug.'/'))
            ->map(function (string $ref): string {
                [, $groupSlug] = explode('/', $ref);

                return $groupSlug;
            })
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function normalizeIntegrationFilter(string $integrationFilter): string
    {
        $integrationFilter = trim($integrationFilter);
        if ($integrationFilter === '' || $integrationFilter === 'all') {
            return 'all';
        }

        $slugs = collect($this->registry->allIntegrations())->pluck('slug')->all();

        return in_array($integrationFilter, $slugs, true) ? $integrationFilter : 'all';
    }

    private function normalizeGroupFilter(string $integrationFilter, string $groupSlugFilter): string
    {
        if ($integrationFilter === 'all') {
            return 'all';
        }

        $groupSlugFilter = trim($groupSlugFilter);
        if ($groupSlugFilter === '' || $groupSlugFilter === 'all') {
            return 'all';
        }

        $allowed = $this->groupSlugsForIntegration($integrationFilter);

        return in_array($groupSlugFilter, $allowed, true) ? $groupSlugFilter : 'all';
    }

    private function humanizeSchedule(?FlowSchedule $schedule): ?string
    {
        if ($schedule === null) {
            return null;
        }

        if (! $schedule->is_active) {
            return 'Inactive';
        }

        return match ($schedule->schedule_type) {
            FlowSchedule::TYPE_EVERY_MINUTES => 'Every '.max(1, (int) $schedule->every_minutes).' minute(s)',
            FlowSchedule::TYPE_HOURLY => 'Hourly',
            FlowSchedule::TYPE_DAILY => 'Daily at '.($schedule->daily_at ?: '--:--'),
            FlowSchedule::TYPE_CRON => 'Cron: '.($schedule->cron_expression ?: '(missing)'),
            default => 'Unknown',
        };
    }
}
