<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StepExecution extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STEP_TYPE_INTEGRATION_DISK = 'integration_disk';

    protected $fillable = [
        'flow_execution_id',
        'flow_step_id',
        'step_class',
        'step_index',
        'step_type',
        'input',
        'output',
        'logs',
        'status',
        'error_message',
        'duration_ms',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'logs' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FlowExecution, $this>
     */
    public function flowExecution(): BelongsTo
    {
        return $this->belongsTo(FlowExecution::class);
    }
}
