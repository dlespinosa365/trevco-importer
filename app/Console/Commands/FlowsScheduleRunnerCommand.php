<?php

namespace App\Console\Commands;

use App\Integrations\FlowDefinitionRegistry;
use App\Jobs\ExecuteIntegrationFlowJob;
use App\Models\FlowSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class FlowsScheduleRunnerCommand extends Command
{
    protected $signature = 'flows:schedule-runner {--limit=50 : Maximum due schedules to process}';

    protected $description = 'Dispatch active due flow schedules and calculate their next run time.';

    public function handle(FlowDefinitionRegistry $registry): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $now = now();

        $ids = FlowSchedule::query()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->orderBy('next_run_at')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('No due flow schedules.');

            return self::SUCCESS;
        }

        $queued = 0;
        $failed = 0;

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, $registry, &$queued, &$failed): void {
                $schedule = FlowSchedule::query()->whereKey($id)->lockForUpdate()->first();
                if ($schedule === null || ! $schedule->is_active || $schedule->next_run_at === null || $schedule->next_run_at->isFuture()) {
                    return;
                }

                try {
                    $registry->resolve($schedule->flow_ref);

                    $triggerPayload = is_array($schedule->trigger_payload) ? $schedule->trigger_payload : [];

                    ExecuteIntegrationFlowJob::dispatchQueued(
                        $schedule->flow_ref,
                        $schedule->run_as_user_id,
                        'schedule',
                        $triggerPayload,
                    );

                    $schedule->forceFill([
                        'last_run_at' => now(),
                        'last_status' => 'queued',
                        'last_error' => null,
                        'next_run_at' => $schedule->calculateNextRunAt($schedule->next_run_at),
                    ])->save();

                    $queued++;
                } catch (Throwable $e) {
                    $schedule->forceFill([
                        'last_run_at' => now(),
                        'last_status' => 'failed',
                        'last_error' => $e->getMessage(),
                        'next_run_at' => $schedule->calculateNextRunAt($schedule->next_run_at),
                    ])->save();

                    $failed++;
                }
            });
        }

        $this->info("Processed schedules: queued={$queued}, failed={$failed}.");

        return self::SUCCESS;
    }
}
