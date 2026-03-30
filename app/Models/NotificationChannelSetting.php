<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationChannelSetting extends Model
{
    protected $fillable = [
        'mail_recipients',
        'slack_webhook_url',
        'teams_workflow_webhook_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mail_recipients' => 'array',
            'slack_webhook_url' => 'encrypted',
            'teams_workflow_webhook_url' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        /** @var self */
        return static::query()->firstOrCreate(
            ['id' => 1],
            [
                'mail_recipients' => [],
                'slack_webhook_url' => null,
                'teams_workflow_webhook_url' => null,
            ]
        );
    }
}
