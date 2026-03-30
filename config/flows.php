<?php

return [
    'integrations_path' => env('FLOWS_INTEGRATIONS_PATH', base_path('integrations')),
    'max_steps' => (int) env('FLOW_MAX_STEPS', 500),
    'queue' => env('FLOWS_QUEUE', 'flows'),

    /*
    | Longest time (seconds) you expect a single integration step to hold a worker, e.g. NetSuite
    | order submission. Used as the baseline for queue retry_after defaults and step job timeout.
    | This does NOT enable step retries ($tries = 1); it prevents the queue driver from releasing
    | a still-running job as "lost" and handing it to another worker (duplicate work).
    */
    'longest_expected_step_seconds' => (int) env('FLOW_LONGEST_STEP_SECONDS', 300),

    /*
    | Scheduled flows:reconcile-stale-running (see routes/console.php). Marks stale RUNNING executions failed.
    */
    'reconcile_stale_running_enabled' => filter_var(
        env('FLOW_RECONCILE_STALE_ENABLED', 'true'),
        FILTER_VALIDATE_BOOL,
    ),

    /*
    | Staleness threshold in minutes passed as --minutes. Default 180 is conservative vs ~5 min NetSuite steps.
    */
    'reconcile_stale_running_minutes' => max(1, (int) env('FLOW_RECONCILE_STALE_MINUTES', 180)),

    /*
    | Minimum seconds between no-op DB touches when steps call DiskFlowContext::heartbeat(). Prevents
    | hammering the database in tight loops. Pass 0 as the argument to heartbeat() to skip throttling.
    */
    'heartbeat_interval_seconds' => max(0, (int) env('FLOW_HEARTBEAT_INTERVAL_SECONDS', 60)),
];
