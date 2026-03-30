<?php

use Illuminate\Support\Str;

$flowsQueue = env('FLOWS_QUEUE', 'flows');

return [

    'name' => env('HORIZON_NAME'),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Long wait thresholds (seconds)
    |--------------------------------------------------------------------------
    |
    | ~50 integrations on ~5-minute schedules can bunch up around the hour.
    | Flow jobs (NetSuite order imports) may take several minutes per step.
    |
    */
    'waits' => [
        "redis:{$flowsQueue}" => (int) env('HORIZON_WAIT_FLOWS_SECONDS', 120),
        'redis:default' => (int) env('HORIZON_WAIT_DEFAULT_SECONDS', 60),
        'redis:notifications' => (int) env('HORIZON_WAIT_NOTIFICATIONS_SECONDS', 30),
        'redis:internal' => (int) env('HORIZON_WAIT_INTERNAL_SECONDS', 60),
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [
        //
    ],

    'silenced_tags' => [
        //
    ],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => (bool) env('HORIZON_FAST_TERMINATION', false),

    'memory_limit' => (int) env('HORIZON_MASTER_MEMORY', 128),

    /*
    |--------------------------------------------------------------------------
    | Supervisors
    |--------------------------------------------------------------------------
    |
    | supervisor-flows: integration pipeline (ExecuteIntegrationFlowJob, ExecuteIntegrationStepJob).
    | Tune maxProcesses down if NetSuite concurrency limits or governance caps are tight.
    |
    | supervisor-general: default, notifications, internal (matches typical queue:work list).
    |
    */
    'defaults' => [
        'supervisor-flows' => [
            'connection' => 'redis',
            'queue' => [$flowsQueue],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => (int) env('HORIZON_FLOWS_MEMORY', 256),
            'tries' => 1,
            'timeout' => (int) env('HORIZON_FLOWS_TIMEOUT', 600),
            'nice' => 0,
        ],
        'supervisor-general' => [
            'connection' => 'redis',
            'queue' => ['default', 'notifications', 'internal'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => (int) env('HORIZON_GENERAL_MEMORY', 128),
            'tries' => 1,
            'timeout' => (int) env('HORIZON_GENERAL_TIMEOUT', 120),
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-flows' => [
                'minProcesses' => (int) env('HORIZON_FLOWS_MIN_PROCESSES', 2),
                'maxProcesses' => (int) env('HORIZON_FLOWS_MAX_PROCESSES', 12),
                'balanceMaxShift' => (int) env('HORIZON_FLOWS_BALANCE_MAX_SHIFT', 1),
                'balanceCooldown' => (int) env('HORIZON_FLOWS_BALANCE_COOLDOWN', 3),
            ],
            'supervisor-general' => [
                'minProcesses' => (int) env('HORIZON_GENERAL_MIN_PROCESSES', 1),
                'maxProcesses' => (int) env('HORIZON_GENERAL_MAX_PROCESSES', 4),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [
            'supervisor-flows' => [
                'maxProcesses' => (int) env('HORIZON_FLOWS_MAX_PROCESSES', 3),
            ],
            'supervisor-general' => [
                'maxProcesses' => (int) env('HORIZON_GENERAL_MAX_PROCESSES', 2),
            ],
        ],
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],

    /*
    |--------------------------------------------------------------------------
    | Long-wait notifications
    |--------------------------------------------------------------------------
    |
    | Fired when queue wait time exceeds the matching entry in "waits" above.
    | Use mail, Slack incoming webhook, and/or SMS. Empty values disable a channel.
    |
    */
    'notifications' => [
        'mail' => env('HORIZON_NOTIFICATIONS_MAIL'),
        'slack_webhook' => env('HORIZON_NOTIFICATIONS_SLACK_WEBHOOK'),
        'slack_channel' => env('HORIZON_NOTIFICATIONS_SLACK_CHANNEL'),
        'sms' => env('HORIZON_NOTIFICATIONS_SMS'),
    ],
];
