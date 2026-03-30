<?php

namespace App\Jobs;

use App\Integrations\ConnectorsHelper;
use App\Integrations\DiskFlowContext;
use App\Integrations\DiskStepRunner;
use App\Integrations\FanOut\FanOutCoordinator;
use App\Integrations\FanOut\FanOutMetadata;
use App\Integrations\FlowDefinitionRegistry;
use App\Integrations\IntegrationFailureNotifier;
use App\Integrations\StepLogCollector;
use App\Jobs\Middleware\BindIntegrationFlowLogContext;
use App\Models\FlowExecution;
use App\Models\StepExecution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Throwable;

class ExecuteIntegrationStepJob implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Worker timeout (seconds) for Horizon / queue workers. Aligns with flows.longest_expected_step_seconds
     * plus margin so legitimate NetSuite (or similar) work is not killed before retry_after lease logic.
     */
    public int $timeout;

    public function __construct(
        public int $flowExecutionId,
        public string $stepClass,
        public int $stepIndex,
        public bool $isFanOutChild = false,
        public ?string $flowRef = null,
    ) {
        $this->onQueue(config('flows.queue', 'flows'));

        $longestStep = (int) config('flows.longest_expected_step_seconds', 300);
        $this->timeout = max(120, $longestStep + 180);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        $tags = [
            'integration:ExecuteIntegrationStepJob',
            'flow_execution:'.$this->flowExecutionId,
            'step:'.$this->stepIndex,
            'step_class:'.class_basename($this->stepClass),
        ];

        if ($this->flowRef !== null && $this->flowRef !== '') {
            $tags[] = 'flow_ref:'.$this->flowRef;
        }

        if ($this->isFanOutChild) {
            $tags[] = 'fan_out:child';
        }

        return $tags;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new BindIntegrationFlowLogContext];
    }

    public function handle(
        FlowDefinitionRegistry $registry,
        DiskStepRunner $runner,
        IntegrationFailureNotifier $failureNotifier,
        FanOutCoordinator $fanOutCoordinator,
    ): void {
        $maxSteps = max(1, (int) config('flows.max_steps', 500));

        if ($this->stepIndex >= $maxSteps) {
            $this->failExecution(
                FlowExecution::query()->find($this->flowExecutionId),
                "Aborted: exceeded maximum steps ({$maxSteps}).",
                null,
                $failureNotifier,
                $fanOutCoordinator,
            );

            return;
        }

        $execution = FlowExecution::query()->find($this->flowExecutionId);

        if ($execution === null) {
            return;
        }

        if ($execution->flow_ref === '') {
            return;
        }

        if ($execution->status !== FlowExecution::STATUS_RUNNING) {
            return;
        }

        $existing = StepExecution::query()
            ->where('flow_execution_id', $execution->id)
            ->where('step_index', $this->stepIndex)
            ->first();

        if (
            $existing !== null
            && $existing->status === StepExecution::STATUS_COMPLETED
            && $existing->step_class === $this->stepClass
        ) {
            return;
        }

        $definition = $registry->resolve($execution->flow_ref);

        $contextData = $execution->context ?? [];
        $triggerPayload = $execution->trigger_payload ?? [];
        $inputSnapshot = array_replace_recursive($contextData, $triggerPayload);

        $logCollector = new StepLogCollector;

        $stepRow = StepExecution::query()->updateOrCreate(
            [
                'flow_execution_id' => $execution->id,
                'step_index' => $this->stepIndex,
            ],
            [
                'flow_step_id' => null,
                'step_class' => $this->stepClass,
                'step_type' => StepExecution::STEP_TYPE_INTEGRATION_DISK,
                'status' => StepExecution::STATUS_RUNNING,
                'input' => $inputSnapshot,
                'output' => null,
                'logs' => null,
                'error_message' => null,
                'duration_ms' => null,
                'started_at' => now(),
                'finished_at' => null,
            ]
        );

        $started = microtime(true);

        try {
            $connectors = new ConnectorsHelper($execution->triggered_by_user_id);

            $diskContext = new DiskFlowContext(
                execution: $execution->fresh(),
                contextSnapshot: $contextData,
                triggerPayload: $triggerPayload,
                mergedConfig: $definition->mergedConfig(),
                connectors: $connectors,
                logs: $logCollector,
                stepExecutionId: $stepRow->id,
            );

            $result = $runner->run($this->stepClass, $diskContext);

            $durationMs = (int) round((microtime(true) - $started) * 1000);

            $basename = class_basename($this->stepClass);
            $stepKey = $this->stepIndex.'_'.$basename;
            $contextData[$stepKey] = $result->output;

            $stepRow->forceFill([
                'status' => StepExecution::STATUS_COMPLETED,
                'output' => $result->output,
                'logs' => $logCollector->toArray(),
                'duration_ms' => $durationMs,
                'finished_at' => now(),
            ])->save();

            $execution->forceFill([
                'context' => $contextData,
            ])->save();

            $nextClass = $result->nextStepClass;
            if ($nextClass === null || $nextClass === '') {
                $entries = $definition->entryClasses;
                $i = array_search($this->stepClass, $entries, true);
                if ($i !== false && isset($entries[$i + 1])) {
                    $nextClass = $entries[$i + 1];
                }
            }

            if ($nextClass === null || $nextClass === '') {
                $this->markFlowExecutionCompleted($execution->fresh(), $fanOutCoordinator);

                return;
            }

            if (! is_string($nextClass) || ! class_exists($nextClass)) {
                throw new \InvalidArgumentException('nextStepClass must be an existing class FQCN or null.');
            }

            $fanOutMeta = FanOutMetadata::resolve($this->stepClass);
            if ($fanOutMeta !== null && $fanOutMeta->enabled) {
                $raw = Arr::get($result->output, $fanOutMeta->itemsPath);
                if (is_array($raw) && array_is_list($raw) && $raw !== []) {
                    try {
                        $fanOutCoordinator->spawnChildRuns(
                            $execution->fresh(),
                            $definition,
                            $contextData,
                            $triggerPayload,
                            $raw,
                            $fanOutMeta,
                            $nextClass,
                            $this->stepIndex + 1,
                        );
                    } catch (Throwable $dispatchException) {
                        $this->failExecution(
                            $execution->fresh(),
                            'Failed to dispatch fan-out runs: '.$dispatchException->getMessage(),
                            $this->stepClass,
                            $failureNotifier,
                            $fanOutCoordinator,
                            $dispatchException,
                        );

                        return;
                    }

                    return;
                }
            }

            try {
                self::dispatch(
                    $execution->id,
                    $nextClass,
                    $this->stepIndex + 1,
                    $this->isFanOutChild,
                    $execution->flow_ref,
                );
            } catch (Throwable $dispatchException) {
                $this->failExecution(
                    $execution->fresh(),
                    'Failed to dispatch next step: '.$dispatchException->getMessage(),
                    $this->stepClass,
                    $failureNotifier,
                    $fanOutCoordinator,
                    $dispatchException,
                );

                return;
            }
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);

            $stepRow->forceFill([
                'status' => StepExecution::STATUS_FAILED,
                'logs' => $logCollector->toArray(),
                'error_message' => $e->getMessage(),
                'duration_ms' => $durationMs,
                'finished_at' => now(),
            ])->save();

            $this->failExecution(
                $execution->fresh(),
                $e->getMessage(),
                $this->stepClass,
                $failureNotifier,
                $fanOutCoordinator,
                $e,
            );
        }
    }

    private function markFlowExecutionCompleted(FlowExecution $execution, FanOutCoordinator $fanOutCoordinator): void
    {
        if ($execution->parent_flow_execution_id !== null) {
            $execution->forceFill([
                'status' => FlowExecution::STATUS_COMPLETED,
                'finished_at' => now(),
            ])->save();

            $fanOutCoordinator->recordChildTerminal($execution->fresh(), true);

            return;
        }

        $execution->forceFill([
            'status' => FlowExecution::STATUS_COMPLETED,
            'finished_at' => now(),
        ])->save();
    }

    private function failExecution(
        ?FlowExecution $execution,
        string $message,
        ?string $failedStepClass,
        IntegrationFailureNotifier $failureNotifier,
        FanOutCoordinator $fanOutCoordinator,
        ?Throwable $exception = null,
    ): void {
        if ($execution === null) {
            return;
        }

        if ($execution->parent_flow_execution_id !== null) {
            $execution->forceFill([
                'status' => FlowExecution::STATUS_FAILED,
                'error_message' => $message,
                'finished_at' => now(),
            ])->save();

            $fanOutCoordinator->recordChildTerminal($execution->fresh(), false, $message);

            return;
        }

        $execution->forceFill([
            'status' => FlowExecution::STATUS_FAILED,
            'error_message' => $message,
            'finished_at' => now(),
        ])->save();

        $failureNotifier->notify(
            $execution->fresh(),
            $exception ?? new \RuntimeException($message),
            $failedStepClass
        );
    }
}
