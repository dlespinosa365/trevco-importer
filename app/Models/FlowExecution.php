<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class FlowExecution extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PARTIAL_COMPLETED = 'partial_completed';

    public function statusUiLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'in queue',
            self::STATUS_RUNNING => 'processing',
            self::STATUS_COMPLETED => 'finished',
            self::STATUS_FAILED, self::STATUS_PARTIAL_COMPLETED => 'error',
            default => 'unknown',
        };
    }

    public function statusUiColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'gray',
            self::STATUS_RUNNING => 'warning',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED, self::STATUS_PARTIAL_COMPLETED => 'danger',
            default => 'gray',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_PARTIAL_COMPLETED,
        ], true);
    }

    protected $fillable = [
        'flow_ref',
        'integration_key',
        'status',
        'parent_flow_execution_id',
        'fan_out_item_reference',
        'aggregated_into_parent_at',
        'triggered_by_user_id',
        'triggered_by_type',
        'context',
        'trigger_payload',
        'error_message',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'trigger_payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'aggregated_into_parent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FlowExecution, $this>
     */
    public function parentFlowExecution(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_flow_execution_id');
    }

    /**
     * @return HasMany<FlowExecution, $this>
     */
    public function childFlowExecutions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_flow_execution_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    /**
     * @return HasMany<StepExecution, $this>
     */
    public function stepExecutions(): HasMany
    {
        return $this->hasMany(StepExecution::class);
    }

    /**
     * Delete this run, all child flow runs (fan-out), and related step executions.
     */
    public function deleteRecursively(): void
    {
        DB::transaction(function (): void {
            foreach ($this->childFlowExecutions()->get() as $child) {
                $child->deleteRecursively();
            }
            $this->stepExecutions()->delete();
            $this->delete();
        });
    }
}
