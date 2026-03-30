<?php

namespace App\Jobs\Middleware;

use App\Jobs\ExecuteIntegrationFlowJob;
use App\Jobs\ExecuteIntegrationStepJob;
use Closure;
use Illuminate\Support\Facades\Context;

/**
 * Binds Laravel log {@see Context} for integration flow jobs so structured logs include flow correlation fields.
 */
class BindIntegrationFlowLogContext
{
    /**
     * Process the queued job.
     *
     * @param  Closure(object): void  $next
     */
    public function handle(object $job, Closure $next): void
    {
        $data = match (true) {
            $job instanceof ExecuteIntegrationFlowJob => [
                'flow_ref' => $job->flowRef,
                'flow_execution_id' => $job->flowExecutionId,
                'integration_job' => 'ExecuteIntegrationFlowJob',
                'triggered_by_type' => $job->triggeredByType,
            ],
            $job instanceof ExecuteIntegrationStepJob => array_merge([
                'flow_execution_id' => $job->flowExecutionId,
                'integration_job' => 'ExecuteIntegrationStepJob',
                'step_index' => $job->stepIndex,
                'step_class' => $job->stepClass,
                'fan_out_child' => $job->isFanOutChild,
            ], $this->optionalFlowRefContext($job->flowRef)),
            default => [],
        };

        if ($data === []) {
            $next($job);

            return;
        }

        Context::scope(fn () => $next($job), $data);
    }

    /**
     * @return array<string, string>
     */
    private function optionalFlowRefContext(?string $flowRef): array
    {
        if ($flowRef === null || $flowRef === '') {
            return [];
        }

        return ['flow_ref' => $flowRef];
    }
}
