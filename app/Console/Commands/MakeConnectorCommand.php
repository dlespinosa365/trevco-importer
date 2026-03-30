<?php

namespace App\Console\Commands;

use App\Enums\ConnectorType;
use App\Models\Connector;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class MakeConnectorCommand extends Command
{
    protected $signature = 'connectors:make';

    protected $description = 'Create an integration connector record with the minimum required credentials.';

    public function handle(): int
    {
        $this->info('Create connector');
        $this->newLine();

        $key = $this->askRequired('Connector key');
        if (Connector::query()->where('key', $key)->exists()) {
            $this->error("Connector key [{$key}] already exists.");

            return self::FAILURE;
        }

        $name = $this->askRequired('Connector display name');
        $type = $this->askConnectorType();
        $userId = $this->askOwnerUserId();
        $credentials = $this->askCredentialsForType($type);

        try {
            Connector::query()->create([
                'user_id' => $userId,
                'key' => $key,
                'name' => $name,
                'connector_type' => $type,
                'credentials' => $credentials,
            ]);
        } catch (ValidationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $scope = $userId === null ? 'global' : "user:{$userId}";
        $this->info("Connector created [{$key}] ({$type->value}, {$scope}).");

        return self::SUCCESS;
    }

    private function askRequired(string $question): string
    {
        while (true) {
            $value = $this->ask($question);
            $value = is_string($value) ? trim($value) : '';

            if ($value === '') {
                $this->error('Value is required.');

                continue;
            }

            return $value;
        }
    }

    private function askConnectorType(): ConnectorType
    {
        $options = [
            ConnectorType::NetSuite->value => 'NetSuite',
            ConnectorType::DummyJson->value => 'Dummy JSON',
            ConnectorType::AmazonVendorCentral->value => 'Amazon Vendor Central',
        ];

        $selected = $this->choice(
            'Connector type',
            array_map(
                fn (string $value, string $label): string => "{$value} ({$label})",
                array_keys($options),
                array_values($options),
            ),
            default: 0,
        );

        preg_match('/^([a-z_]+)/', (string) $selected, $matches);
        $value = $matches[1] ?? ConnectorType::NetSuite->value;

        return ConnectorType::from($value);
    }

    private function askOwnerUserId(): ?int
    {
        while (true) {
            $value = $this->ask('Owner user id (leave empty for global)', '');
            $value = is_string($value) ? trim($value) : '';

            if ($value === '') {
                return null;
            }

            if (! ctype_digit($value)) {
                $this->error('User id must be a numeric value.');

                continue;
            }

            $id = (int) $value;
            if (! User::query()->whereKey($id)->exists()) {
                $this->error("User id {$id} does not exist.");

                continue;
            }

            return $id;
        }
    }

    /**
     * @return array<string, string>
     */
    private function askCredentialsForType(ConnectorType $type): array
    {
        return match ($type) {
            ConnectorType::DummyJson => [
                'base_url' => $this->askRequired('Dummy JSON base_url'),
            ],
            ConnectorType::NetSuite => [
                'account_id' => $this->askRequired('NetSuite account_id'),
                'client_id' => $this->askRequired('NetSuite client_id (consumer key)'),
                'client_secret' => $this->askRequired('NetSuite client_secret (consumer secret)'),
                'token_id' => $this->askRequired('NetSuite token_id'),
                'token_secret' => $this->askRequired('NetSuite token_secret'),
                'restlet_url' => $this->askRequired('NetSuite RESTlet URL'),
            ],
            ConnectorType::AmazonVendorCentral => [
                'client_id' => $this->askRequired('Amazon client_id'),
                'client_secret' => $this->askRequired('Amazon client_secret'),
                'refresh_token' => $this->askRequired('Amazon refresh_token'),
                'aws_access_key_id' => $this->askRequired('AWS access key id'),
                'aws_secret_access_key' => $this->askRequired('AWS secret access key'),
                'region' => $this->askRequired('AWS region (e.g. eu-west-1)'),
                'sp_api_region' => $this->askRequired('SP API region (na, eu, fe)'),
            ],
        };
    }
}
