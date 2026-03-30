<?php

namespace App\Console\Commands;

use App\Jobs\ExecuteIntegrationFlowJob;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use JsonException;

class FlowsRunCommand extends Command
{
    protected $signature = 'flows:run
        {flow_ref : Three-part ref, e.g. demo/default/hello}
        {--user= : User ID for triggered_by_user_id and connection scoping}
        {--trigger=manual : Trigger type label (manual, api, webhook, schedule, ...)}
        {--payload= : JSON object merged into trigger_payload}
        {--step= : Optional FQCN for the first step (overrides flow.php entry; e.g. webhook ingress)}
        {--sync : Run in the current process (temporarily uses the sync queue driver so all steps finish here)}';

    protected $description = 'Dispatch a disk integration flow onto the flows queue (or run it synchronously with --sync).';

    public function handle(): int
    {
        $flowRef = trim($this->argument('flow_ref'), '/');
        $userId = $this->option('user');

        if ($userId !== null && $userId !== '') {
            $userId = (int) $userId;
            if (! User::query()->whereKey($userId)->exists()) {
                $this->error("User id {$userId} does not exist.");

                return self::FAILURE;
            }
        } else {
            $userId = null;
        }

        $payloadRaw = (string) $this->option('payload');
        $payload = [];

        if ($payloadRaw !== '') {
            try {
                $decoded = json_decode($payloadRaw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $this->error('Invalid JSON for --payload: '.$e->getMessage());

                return self::FAILURE;
            }

            if (! is_array($decoded)) {
                $this->error('--payload must decode to a JSON object.');

                return self::FAILURE;
            }

            $payload = $decoded;
        }

        $trigger = (string) $this->option('trigger');
        $stepOpt = $this->option('step');
        $initialStep = ($stepOpt !== null && $stepOpt !== '') ? (string) $stepOpt : null;

        $runSync = (bool) $this->option('sync');
        $previousQueueDefault = config('queue.default');

        if ($runSync) {
            Config::set('queue.default', 'sync');
        }

        try {
            ExecuteIntegrationFlowJob::dispatchQueued(
                $flowRef,
                $userId,
                $trigger,
                $payload,
                $initialStep,
            );
        } finally {
            if ($runSync) {
                Config::set('queue.default', $previousQueueDefault);
            }
        }

        if ($runSync) {
            $this->info("Finished flow [{$flowRef}] synchronously in this process.");
        } else {
            $this->info("Dispatched flow [{$flowRef}] to queue [".config('flows.queue', 'flows').'].');
        }

        return self::SUCCESS;
    }
}
