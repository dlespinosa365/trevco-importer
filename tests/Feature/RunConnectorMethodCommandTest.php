<?php

use App\Enums\ConnectorType;
use App\Models\Connector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('runs a connector method without parameters', function () {
    Http::fake([
        'dummyjson.com/carts*' => Http::response(['carts' => [], 'total' => 0], 200),
    ]);

    $connector = Connector::factory()->create([
        'key' => 'dummy_runner_no_params',
        'name' => 'Dummy Runner',
        'connector_type' => ConnectorType::DummyJson,
        'credentials' => [
            'base_url' => 'https://dummyjson.com',
        ],
    ]);

    $this->artisan('connectors:run-method')
        ->expectsQuestion('Connector key', "{$connector->key} ({$connector->connector_type->value})")
        ->expectsQuestion('Connector method', 'testConnection()')
        ->expectsOutput('Method executed successfully.')
        ->assertExitCode(0);
});

it('runs a connector method with prompted parameters', function () {
    Http::fake([
        'dummyjson.com/carts*' => Http::response(['carts' => [], 'total' => 0], 200),
    ]);

    $connector = Connector::factory()->create([
        'key' => 'dummy_runner_with_params',
        'name' => 'Dummy Runner',
        'connector_type' => ConnectorType::DummyJson,
        'credentials' => [
            'base_url' => 'https://dummyjson.com',
        ],
    ]);

    $this->artisan('connectors:run-method')
        ->expectsQuestion('Connector key', "{$connector->key} ({$connector->connector_type->value})")
        ->expectsQuestion('Connector method', 'getOrders(array query?)')
        ->expectsQuestion('Value for query (array, optional)', '{"limit":2}')
        ->expectsOutput('Method executed successfully.')
        ->assertExitCode(0);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/carts?limit=2'));
});
