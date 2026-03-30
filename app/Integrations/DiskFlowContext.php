<?php

namespace App\Integrations;

use App\Models\FlowExecution;
use App\Models\StepExecution;

final class DiskFlowContext
{
    /**
     * Monotonic time of last successful heartbeat write (for default throttling).
     */
    private ?float $lastHeartbeatAt = null;

    public function __construct(
        public FlowExecution $execution,
        private array $contextSnapshot,
        private readonly array $triggerPayload,
        private readonly array $mergedConfig,
        public ConnectorsHelper $connectors,
        public StepLogCollector $logs,
        private readonly ?int $stepExecutionId = null,
    ) {}

    /**
     * Refresh **updated_at** on the flow execution (and current step row when known) so
     * **flows:reconcile-stale-running** does not treat long-running work as orphaned.
     *
     * Call periodically inside slow **Step::run()** loops (API pagination, large batches). By default
     * writes are throttled using **config('flows.heartbeat_interval_seconds')**; pass **0** for
     * **$minimumIntervalSeconds** to force an immediate write (e.g. tests).
     *
     * @param  int|null  $minimumIntervalSeconds  **null** = use config; **0** = no throttle
     */
    public function heartbeat(?int $minimumIntervalSeconds = null): bool
    {
        $configured = (int) config('flows.heartbeat_interval_seconds', 60);
        $interval = $minimumIntervalSeconds ?? $configured;
        $now = microtime(true);

        if ($interval > 0 && $this->lastHeartbeatAt !== null && ($now - $this->lastHeartbeatAt) < $interval) {
            return false;
        }

        $touchedFlow = FlowExecution::query()
            ->whereKey($this->execution->id)
            ->where('status', FlowExecution::STATUS_RUNNING)
            ->update(['updated_at' => now()]) > 0;

        if ($touchedFlow && $this->stepExecutionId !== null) {
            StepExecution::query()
                ->whereKey($this->stepExecutionId)
                ->where('status', StepExecution::STATUS_RUNNING)
                ->update(['updated_at' => now()]);
        }

        if ($touchedFlow) {
            $this->lastHeartbeatAt = $now;
        }

        return $touchedFlow;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->contextSnapshot;
    }

    /**
     * @return array<string, mixed>
     */
    public function triggerPayload(): array
    {
        return $this->triggerPayload;
    }

    /**
     * @return array<string, mixed>
     */
    public function mergedConfig(): array
    {
        return $this->mergedConfig;
    }
}
