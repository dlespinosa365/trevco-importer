<?php

namespace App\Console\Commands;

use App\Integrations\FanOut\FanOutCoordinator;
use App\Integrations\IntegrationFailureNotifier;
use App\Models\FlowExecution;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class FlowsReconcileStaleRunningCommand extends Command
{
    protected $signature = 'flows:reconcile-stale-running
                            {--minutes=120 : Consider RUNNING stale when updated_at is older than this many minutes}
                            {--dry-run : List matching executions without changing them}';

    protected $description = 'Mark stale RUNNING flow executions as failed (no progress heartbeat via updated_at).';

    public function handle(
        IntegrationFailureNotifier $failureNotifier,
        FanOutCoordinator $fanOutCoordinator,
    ): int {
        $minutes = max(1, (int) $this->option('minutes'));
        $cutoff = now()->subMinutes($minutes);
        $dryRun = (bool) $this->option('dry-run');

        $query = FlowExecution::query()
            ->where('status', FlowExecution::STATUS_RUNNING)
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id');

        $count = $query->count();

        if ($count === 0) {
            $this->info('No stale RUNNING executions found.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} stale RUNNING execution(s) (updated_at before {$cutoff->toIso8601String()}).");
        $this->warn('Long single-step flows must use a --minutes value greater than the slowest step, or they may be flagged incorrectly.');

        if ($dryRun) {
            foreach ($query->cursor() as $execution) {
                /** @var FlowExecution $execution */
                $this->line("  #{$execution->id} {$execution->flow_ref} updated_at={$execution->updated_at}");
            }

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($query->cursor() as $execution) {
            /** @var FlowExecution $execution */
            $locked = FlowExecution::query()->whereKey($execution->id)->first();
            if ($locked === null || $locked->status !== FlowExecution::STATUS_RUNNING) {
                continue;
            }

            $message = "Stale run: no progress for at least {$minutes} minute(s) (reconciled).";

            try {
                if ($locked->parent_flow_execution_id !== null) {
                    $locked->forceFill([
                        'status' => FlowExecution::STATUS_FAILED,
                        'error_message' => $message,
                        'finished_at' => now(),
                    ])->save();
                    $fanOutCoordinator->recordChildTerminal($locked->fresh(), false, $message);
                } else {
                    $locked->forceFill([
                        'status' => FlowExecution::STATUS_FAILED,
                        'error_message' => $message,
                        'finished_at' => now(),
                    ])->save();
                    $failureNotifier->notify(
                        $locked->fresh(),
                        new RuntimeException($message),
                        null,
                    );
                }

                $failed++;
                $this->line("  Marked failed: #{$locked->id} {$locked->flow_ref}");
            } catch (Throwable $e) {
                $this->error("  Failed to reconcile execution #{$locked->id}: {$e->getMessage()}");
            }
        }

        $this->info("Reconciled {$failed} execution(s).");

        return self::SUCCESS;
    }
}
