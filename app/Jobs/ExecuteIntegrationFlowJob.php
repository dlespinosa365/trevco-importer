<?php

namespace App\Jobs;

use App\Integrations\Contracts\Step;
use App\Integrations\DiskFlowDefinition;
use App\Integrations\FlowDefinitionRegistry;
use App\Integrations\IntegrationFailureNotifier;
use App\Jobs\Middleware\BindIntegrationFlowLogContext;
use App\Models\FlowExecution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;
use Throwable;

class ExecuteIntegrationFlowJob implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * @param  class-string<Step>|null  $initialStepClass  When set (e.g. webhook ingress), overrides the first step from flow.php entry.
     */
    public function __construct(
        public string $flowRef,
        public ?int $triggeredByUserId,
        public string $triggeredByType,
        public array $triggerPayload = [],
        public ?string $initialStepClass = null,
        public ?int $flowExecutionId = null,
    ) {
        $this->onQueue(config('flows.queue', 'flows'));
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        $tags = [
            'integration:ExecuteIntegrationFlowJob',
            'flow_ref:'.$this->flowRef,
            'trigger:'.$this->triggeredByType,
        ];

        if ($this->flowExecutionId !== null) {
            $tags[] = 'flow_execution:'.$this->flowExecutionId;
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

    public static function dispatchQueued(
        string $flowRef,
        ?int $triggeredByUserId,
        string $triggeredByType,
        array $triggerPayload = [],
        ?string $initialStepClass = null,
    ): FlowExecution {
        $trimmedFlowRef = trim($flowRef, '/');
        $integrationKey = explode('/', $trimmedFlowRef)[0] ?? $trimmedFlowRef;

        $flowExecution = FlowExecution::query()->create([
            'flow_ref' => $trimmedFlowRef,
            'integration_key' => $integrationKey,
            'status' => FlowExecution::STATUS_PENDING,
            'triggered_by_user_id' => $triggeredByUserId,
            'triggered_by_type' => $triggeredByType,
            'context' => [],
            'trigger_payload' => $triggerPayload,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);

        self::dispatch(
            $trimmedFlowRef,
            $triggeredByUserId,
            $triggeredByType,
            $triggerPayload,
            $initialStepClass,
            $flowExecution->id,
        );

        return $flowExecution;
    }

    public function handle(FlowDefinitionRegistry $registry): void
    {
        $definition = $registry->resolve($this->flowRef);
        $execution = $this->flowExecutionId !== null
            ? FlowExecution::query()->find($this->flowExecutionId)
            : null;

        if (! $definition->isActive) {
            if ($execution !== null) {
                $execution->forceFill([
                    'integration_key' => $definition->integrationKey,
                    'status' => FlowExecution::STATUS_FAILED,
                    'error_message' => 'Flow is not active.',
                    'started_at' => now(),
                    'finished_at' => now(),
                ])->save();
            } else {
                FlowExecution::query()->create([
                    'flow_ref' => $this->flowRef,
                    'integration_key' => $definition->integrationKey,
                    'status' => FlowExecution::STATUS_FAILED,
                    'triggered_by_user_id' => $this->triggeredByUserId,
                    'triggered_by_type' => $this->triggeredByType,
                    'context' => [],
                    'trigger_payload' => $this->triggerPayload,
                    'error_message' => 'Flow is not active.',
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);
            }

            return;
        }

        $firstStep = $this->resolveFirstStepClass($definition);

        if ($execution !== null) {
            $execution->forceFill([
                'integration_key' => $definition->integrationKey,
                'status' => FlowExecution::STATUS_RUNNING,
                'error_message' => null,
                'started_at' => $execution->started_at ?? now(),
                'finished_at' => null,
            ])->save();
        } else {
            $execution = FlowExecution::query()->create([
                'flow_ref' => $this->flowRef,
                'integration_key' => $definition->integrationKey,
                'status' => FlowExecution::STATUS_RUNNING,
                'triggered_by_user_id' => $this->triggeredByUserId,
                'triggered_by_type' => $this->triggeredByType,
                'context' => [],
                'trigger_payload' => $this->triggerPayload,
                'error_message' => null,
                'started_at' => now(),
                'finished_at' => null,
            ]);
        }

        try {
            ExecuteIntegrationStepJob::dispatch(
                $execution->id,
                $firstStep,
                0,
                false,
                $execution->flow_ref,
            );
        } catch (Throwable $e) {
            $execution->forceFill([
                'status' => FlowExecution::STATUS_FAILED,
                'error_message' => 'Failed to dispatch first step: '.$e->getMessage(),
                'finished_at' => now(),
            ])->save();

            app(IntegrationFailureNotifier::class)->notify(
                $execution->fresh(),
                $e,
                $firstStep,
            );
        }
    }

    /**
     * @return class-string<Step>
     */
    private function resolveFirstStepClass(DiskFlowDefinition $definition): string
    {
        $candidate = $this->initialStepClass;
        if ($candidate === null || $candidate === '') {
            return $definition->firstEntry();
        }

        if (! class_exists($candidate)) {
            throw new InvalidArgumentException("initialStepClass [{$candidate}] does not exist or is not autoloadable.");
        }

        if (! is_subclass_of($candidate, Step::class)) {
            throw new InvalidArgumentException('initialStepClass must implement '.Step::class.'.');
        }

        return $candidate;
    }
}
