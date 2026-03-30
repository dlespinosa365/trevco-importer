<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlowNotificationSetting extends Model
{
    protected $fillable = [
        'flow_ref',
        'mail_enabled',
        'slack_enabled',
        'teams_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mail_enabled' => 'boolean',
            'slack_enabled' => 'boolean',
            'teams_enabled' => 'boolean',
        ];
    }
}
