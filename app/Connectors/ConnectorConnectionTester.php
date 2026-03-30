<?php

namespace App\Connectors;

use App\Connectors\Contracts\TestsConnection;
use App\Enums\ConnectorType;
use App\Integrations\ConnectorsHelper;
use App\Models\Connector;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ConnectorConnectionTester
{
    public static function supports(?ConnectorType $type): bool
    {
        return $type !== null && match ($type) {
            ConnectorType::NetSuite, ConnectorType::DummyJson => true,
            default => false,
        };
    }

    public function test(Connector $connector): ConnectorConnectionTestResult
    {
        $type = $connector->connector_type;
        if (! self::supports($type)) {
            return new ConnectorConnectionTestResult(false, __('Connection test is not available for this connector type.'));
        }

        $helper = new ConnectorsHelper($connector->user_id);

        try {
            $client = match ($type) {
                ConnectorType::NetSuite => $helper->netsuite($connector->key),
                ConnectorType::DummyJson => $helper->dummyJson($connector->key),
                default => throw new RuntimeException('Unsupported connector.'),
            };

            if (! $client instanceof TestsConnection) {
                throw new RuntimeException('Connector client does not support connection tests.');
            }

            $client->testConnection();
        } catch (Throwable $e) {
            $this->persistResult($connector, false, Str::limit($e->getMessage(), 2000));

            return new ConnectorConnectionTestResult(false, $e->getMessage());
        }

        $this->persistResult($connector, true, null);

        return new ConnectorConnectionTestResult(true);
    }

    private function persistResult(Connector $connector, bool $success, ?string $error): void
    {
        $connector->forceFill([
            'last_connection_test_at' => now(),
            'last_connection_test_success' => $success,
            'last_connection_test_error' => $error,
        ])->save();
    }
}
