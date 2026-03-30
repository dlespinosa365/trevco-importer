<?php

namespace App\Models;

use App\Enums\ConnectorType;
use Database\Factories\ConnectorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Connector extends Model
{
    /** @use HasFactory<ConnectorFactory> */
    use HasFactory;

    protected $table = 'integration_connections';

    protected $fillable = [
        'user_id',
        'key',
        'name',
        'connector_type',
        'credentials',
        'last_connection_test_at',
        'last_connection_test_success',
        'last_connection_test_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'connector_type' => ConnectorType::class,
            'credentials' => 'encrypted:array',
            'last_connection_test_at' => 'datetime',
            'last_connection_test_success' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
