<?php

namespace Database\Factories;

use App\Enums\ConnectorType;
use App\Models\Connector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connector>
 */
class ConnectorFactory extends Factory
{
    protected $model = Connector::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'key' => fake()->unique()->slug().'_'.fake()->numerify('##'),
            'name' => fake()->words(2, true),
            'connector_type' => ConnectorType::NetSuite,
            'credentials' => [
                'account_id' => 'TSTDRV123',
                'client_id' => fake()->uuid(),
                'client_secret' => fake()->sha256(),
                'token_id' => fake()->uuid(),
                'token_secret' => fake()->sha256(),
            ],
        ];
    }
}
