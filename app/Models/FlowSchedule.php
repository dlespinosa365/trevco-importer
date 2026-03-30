<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class FlowSchedule extends Model
{
    public const TYPE_EVERY_MINUTES = 'every_minutes';

    public const TYPE_HOURLY = 'hourly';

    public const TYPE_DAILY = 'daily';

    public const TYPE_CRON = 'cron';

    protected $fillable = [
        'flow_ref',
        'is_active',
        'timezone',
        'schedule_type',
        'every_minutes',
        'daily_at',
        'cron_expression',
        'run_as_user_id',
        'trigger_payload',
        'next_run_at',
        'last_run_at',
        'last_status',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'every_minutes' => 'integer',
            'trigger_payload' => 'array',
            'next_run_at' => 'immutable_datetime',
            'last_run_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function runAsUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_as_user_id');
    }

    public function calculateNextRunAt(?CarbonImmutable $from = null): CarbonImmutable
    {
        $timezone = $this->timezone !== '' ? $this->timezone : 'UTC';
        $base = ($from ?? CarbonImmutable::now())->setTimezone($timezone);

        return match ($this->schedule_type) {
            self::TYPE_EVERY_MINUTES => $base
                ->addMinutes(max(1, (int) $this->every_minutes))
                ->setTimezone('UTC'),
            self::TYPE_HOURLY => $base
                ->addHour()
                ->startOfHour()
                ->setTimezone('UTC'),
            self::TYPE_DAILY => $this->calculateDailyNextRun($base)->setTimezone('UTC'),
            self::TYPE_CRON => CarbonImmutable::instance(
                CronExpression::factory((string) $this->cron_expression)->getNextRunDate(
                    $base->toDateTimeString(),
                    0,
                    false,
                    $timezone,
                )
            )->setTimezone('UTC'),
            default => throw new InvalidArgumentException("Unsupported schedule type [{$this->schedule_type}]."),
        };
    }

    private function calculateDailyNextRun(CarbonImmutable $base): CarbonImmutable
    {
        $value = is_string($this->daily_at) ? $this->daily_at : '00:00:00';
        $parts = explode(':', $value);
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);

        $next = $base->setTime($hour, $minute, 0);
        if ($next->lessThanOrEqualTo($base)) {
            $next = $next->addDay();
        }

        return $next;
    }
}
