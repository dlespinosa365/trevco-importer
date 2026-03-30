<?php

namespace App\Integrations\FanOut;

use App\Integrations\DiskFlowDefinition;
use App\Integrations\IntegrationFailureNotifier;
use App\Jobs\ExecuteIntegrationStepJob;
use App\Models\FlowExecution;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FanOutCoordinator
{
    public function __construct(
        private IntegrationFailureNotifier $failureNotifier,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function spawnChildRuns(
        FlowExecution $parent,
        DiskFlowDefinition $definition,
        array $parentContext,
        array $triggerPayload,
        array $items,
        FanOutConfig $config,
        string $nextStepClass,
        int $nextStepIndex,
    ): void {
        $expected = count($items);
        $baseContext = $parentContext;
        unset($baseContext['_fan_out']);

        $fanOutState = [
            'expected' => $expected,
            'completed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $baseContext['_fan_out'] = $fanOutState;

        $parent->forceFill([
            'context' => $baseContext,
        ])->save();

        $fanOutStepIndex = $nextStepIndex - 1;
        if ($fanOutStepIndex < 0) {
            throw new RuntimeException('Fan-out spawn requires a positive next step index.');
        }

        $fanOutStepClass = $definition->entryClasses[$fanOutStepIndex] ?? null;
        if (! is_string($fanOutStepClass) || $fanOutStepClass === '') {
            throw new RuntimeException("Fan-out step class missing at entry index [{$fanOutStepIndex}].");
        }

        $fanOutStepKey = $fanOutStepIndex.'_'.class_basename($fanOutStepClass);

        foreach ($items as $item) {
            if (! is_array($item)) {
                $item = ['value' => $item];
            }

            $reference = $this->referenceForItem($item, $config);

            $childContext = $baseContext;
            unset($childContext['_fan_out']);
            $this->narrowFanOutStepOutputForChild($childContext, $fanOutStepKey, $config->itemsPath, $item);
            $childContext['_fan_out_item'] = $item;
            $childContext['_fan_out_reference'] = $reference;

            $child = FlowExecution::query()->create([
                'flow_ref' => $parent->flow_ref,
                'integration_key' => $parent->integration_key,
                'status' => FlowExecution::STATUS_RUNNING,
                'triggered_by_user_id' => $parent->triggered_by_user_id,
                'triggered_by_type' => $parent->triggered_by_type,
                'parent_flow_execution_id' => $parent->id,
                'fan_out_item_reference' => $reference,
                'context' => $childContext,
                'trigger_payload' => $triggerPayload,
                'error_message' => null,
                'started_at' => now(),
                'finished_at' => null,
            ]);

            ExecuteIntegrationStepJob::dispatch(
                $child->id,
                $nextStepClass,
                $nextStepIndex,
                isFanOutChild: true,
                flowRef: $child->flow_ref,
            );
        }
    }

    /**
     * Replace the fan-out step's list at `$itemsPath` with a single-element list so child runs
     * see one item in the same shape as the parent output (and in step input snapshots), not the full array.
     *
     * @param  array<string, mixed>  $childContext
     */
    private function narrowFanOutStepOutputForChild(
        array &$childContext,
        string $fanOutStepKey,
        string $itemsPath,
        array $item,
    ): void {
        $stepOutput = $childContext[$fanOutStepKey] ?? null;
        if (! is_array($stepOutput)) {
            return;
        }

        $narrowed = unserialize(serialize($stepOutput));
        Arr::set($narrowed, $itemsPath, [$item]);
        $childContext[$fanOutStepKey] = $narrowed;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function referenceForItem(array $item, FanOutConfig $config): string
    {
        $ref = Arr::get($item, $config->itemReferenceKey);

        if ($ref !== null && $ref !== '') {
            return is_scalar($ref) ? (string) $ref : (string) json_encode($ref, JSON_UNESCAPED_UNICODE);
        }

        return substr(hash('xxh3', serialize($item)), 0, 24);
    }

    public function recordChildTerminal(FlowExecution $child, bool $success, ?string $message = null): void
    {
        if ($child->parent_flow_execution_id === null) {
            return;
        }

        DB::transaction(function () use ($child, $success, $message): void {
            $lockedChild = FlowExecution::query()
                ->whereKey($child->id)
                ->lockForUpdate()
                ->first();

            if ($lockedChild === null || $lockedChild->aggregated_into_parent_at !== null) {
                return;
            }

            $lockedChild->forceFill([
                'aggregated_into_parent_at' => now(),
            ])->save();

            $parent = FlowExecution::query()
                ->whereKey($lockedChild->parent_flow_execution_id)
                ->lockForUpdate()
                ->first();

            if ($parent === null || $parent->status !== FlowExecution::STATUS_RUNNING) {
                return;
            }

            $ctx = $parent->context ?? [];
            $fan = $ctx['_fan_out'] ?? null;

            if (! is_array($fan)) {
                return;
            }

            $fan['completed'] = (int) ($fan['completed'] ?? 0) + 1;

            if ($success) {
                $fan['succeeded'] = (int) ($fan['succeeded'] ?? 0) + 1;
            } else {
                $fan['failed'] = (int) ($fan['failed'] ?? 0) + 1;
                $errors = $fan['errors'] ?? [];
                $errors[] = [
                    'reference' => $lockedChild->fan_out_item_reference,
                    'message' => $message ?? 'Failed',
                ];
                $fan['errors'] = $errors;
            }

            $ctx['_fan_out'] = $fan;
            $parent->forceFill(['context' => $ctx]);

            $expected = (int) ($fan['expected'] ?? 0);
            $completed = (int) ($fan['completed'] ?? 0);

            if ($completed < $expected) {
                $parent->save();

                return;
            }

            $succeeded = (int) ($fan['succeeded'] ?? 0);
            $failed = (int) ($fan['failed'] ?? 0);

            if ($failed === 0) {
                $status = FlowExecution::STATUS_COMPLETED;
                $err = null;
            } elseif ($succeeded === 0) {
                $status = FlowExecution::STATUS_FAILED;
                $err = "All {$failed} fan-out run(s) failed.";
            } else {
                $status = FlowExecution::STATUS_PARTIAL_COMPLETED;
                $err = "Fan-out: {$succeeded} succeeded, {$failed} failed. Details in execution context under _fan_out.errors.";
            }

            $parent->forceFill([
                'status' => $status,
                'finished_at' => now(),
                'error_message' => $err,
            ])->save();

            if ($status === FlowExecution::STATUS_FAILED || $failed > 0) {
                $this->failureNotifier->notify(
                    $parent->fresh(),
                    new RuntimeException($err ?? 'Fan-out failures'),
                    null,
                );
            }
        });
    }
}
