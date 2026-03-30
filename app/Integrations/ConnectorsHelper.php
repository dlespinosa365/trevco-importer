<?php

namespace App\Integrations;

use App\Connectors\Vendors\Amazon\AmazonVendorCentralConnector;
use App\Connectors\Vendors\DummyJson\DummyJsonConnector;
use App\Connectors\Vendors\NetSuite\NetSuiteConnector;
use App\Models\Connector;
use InvalidArgumentException;

final class ConnectorsHelper
{
    public function __construct(private ?int $userId) {}

    /**
     * Decrypted credentials for this connection key.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when no connector row exists for the key (after user + global fallback), or when credentials are empty.
     */
    public function credentials(string $key): array
    {
        $trimmedKey = trim($key);
        if ($trimmedKey === '') {
            throw new InvalidArgumentException('Connector key cannot be empty.');
        }

        if ($this->userId !== null) {
            $candidate = Connector::query()
                ->where('key', $trimmedKey)
                ->where('user_id', $this->userId)
                ->first();

            if ($this->connectorRowHasUsableCredentials($candidate)) {
                /** @var Connector $candidate */
                return $candidate->credentials ?? [];
            }
        }

        $row = Connector::query()
            ->where('key', $trimmedKey)
            ->whereNull('user_id')
            ->first();

        if ($row === null) {
            $hint = $this->userId === null
                ? 'No global connector exists for this key.'
                : 'No connector exists for this user and no global fallback exists for this key.';

            throw new InvalidArgumentException(
                "Integration connector [{$trimmedKey}] not found. {$hint} Create or fix the connector in the admin panel.",
            );
        }

        $credentials = $row->credentials ?? [];
        if (! is_array($credentials) || $credentials === []) {
            throw new InvalidArgumentException(
                "Integration connector [{$trimmedKey}] has no credentials configured. Open it in the admin panel and save the required fields.",
            );
        }

        return $credentials;
    }

    private function connectorRowHasUsableCredentials(?Connector $connector): bool
    {
        if ($connector === null) {
            return false;
        }

        $credentials = $connector->credentials;

        return is_array($credentials) && $credentials !== [];
    }

    public function netsuite(string $key): NetSuiteConnector
    {
        return new NetSuiteConnector($this->credentials($key));
    }

    public function dummyJson(string $key): DummyJsonConnector
    {
        return new DummyJsonConnector($this->credentials($key));
    }

    public function amazonVendorCentral(string $key): AmazonVendorCentralConnector
    {
        return new AmazonVendorCentralConnector($this->credentials($key));
    }
}
