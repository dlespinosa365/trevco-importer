<?php

use App\Connectors\ConnectorConnectionTester;
use App\Enums\ConnectorType;
use App\Integrations\ConnectorsHelper;
use App\Models\Connector;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate(Roles::ADMIN, 'web');
});

it('stores a connector and resolves credentials via ConnectorsHelper', function () {
    $connector = Connector::factory()->create([
        'user_id' => null,
        'key' => 'netsuite_sb1',
        'name' => 'NetSuite Sandbox',
        'connector_type' => ConnectorType::NetSuite,
        'credentials' => [
            'account_id' => 'ACCT1',
            'client_id' => 'cid',
            'client_secret' => 'csec',
            'token_id' => 'tid',
            'token_secret' => 'tsec',
            'restlet_url' => 'https://3521821-sb1.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=1&deploy=1',
        ],
    ]);

    expect($connector->connector_type)->toBe(ConnectorType::NetSuite);

    $helper = new ConnectorsHelper(null);
    expect($helper->credentials('netsuite_sb1'))->toMatchArray([
        'account_id' => 'ACCT1',
        'client_id' => 'cid',
    ]);
});

it('scopes ConnectorsHelper credentials by user id', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    Connector::factory()->create([
        'user_id' => $user->id,
        'key' => 'scoped_key',
        'connector_type' => ConnectorType::NetSuite,
        'credentials' => [
            'account_id' => 'U1',
            'client_id' => 'a',
            'client_secret' => 'b',
            'token_id' => 'c',
            'token_secret' => 'd',
            'restlet_url' => 'https://3521821-sb1.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=1&deploy=1',
        ],
    ]);

    expect((new ConnectorsHelper($user->id))->credentials('scoped_key'))->not->toBe([]);
    expect(fn () => (new ConnectorsHelper(null))->credentials('scoped_key'))
        ->toThrow(InvalidArgumentException::class, 'Integration connector [scoped_key] not found');
});

it('falls back to global connector credentials when scoped connector is missing', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    Connector::factory()->create([
        'user_id' => null,
        'key' => 'global_dummy',
        'connector_type' => ConnectorType::DummyJson,
        'credentials' => [
            'base_url' => 'https://dummyjson.com',
        ],
    ]);

    expect((new ConnectorsHelper($user->id))->credentials('global_dummy'))
        ->toMatchArray([
            'base_url' => 'https://dummyjson.com',
        ]);
});

it('falls back to global connector credentials when scoped row has empty credentials', function () {
    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    Connector::query()->create([
        'user_id' => $user->id,
        'key' => 'dummy-json',
        'name' => 'Empty user stub',
        'connector_type' => ConnectorType::DummyJson,
        'credentials' => [],
    ]);

    Connector::query()->create([
        'user_id' => null,
        'key' => 'dummy-json',
        'name' => 'Global source',
        'connector_type' => ConnectorType::DummyJson,
        'credentials' => [
            'base_url' => 'https://dummyjson.com',
        ],
    ]);

    expect((new ConnectorsHelper($user->id))->credentials('dummy-json'))
        ->toMatchArray([
            'base_url' => 'https://dummyjson.com',
        ]);
});

it('exposes NetSuite client that signs SuiteQL with TBA', function () {
    Http::fake([
        '*.suitetalk.api.netsuite.com/*' => Http::response(['items' => []], 200),
    ]);

    $helper = new ConnectorsHelper(null);
    expect(fn () => $helper->netsuite('ns_key'))
        ->toThrow(InvalidArgumentException::class, 'Integration connector [ns_key] not found');

    Connector::factory()->create([
        'user_id' => null,
        'key' => 'ns_key',
        'connector_type' => ConnectorType::NetSuite,
        'credentials' => [
            'account_id' => '3521821_SB1',
            'client_id' => 'ck',
            'client_secret' => 'cs',
            'token_id' => 'tid',
            'token_secret' => 'ts',
            'restlet_url' => 'https://3521821-sb1.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=1&deploy=1',
        ],
    ]);

    $result = (new ConnectorsHelper(null))->netsuite('ns_key')->executeQuery('SELECT 1');
    expect($result)->toBe(['items' => []]);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '3521821-sb1.suitetalk.api.netsuite.com'));
    $auth = Http::recorded()[0][0]->header('Authorization');
    $line = is_array($auth) ? ($auth[0] ?? '') : (string) $auth;
    expect($line)->toContain('OAuth realm=');
    expect($line)->toContain('3521821_SB1');
    $prefer = Http::recorded()[0][0]->header('Prefer');
    $preferValue = is_array($prefer) ? ($prefer[0] ?? '') : (string) $prefer;
    expect($preferValue)->toBe('transient');
});

it('exposes DummyJson client getOrders', function () {
    Http::fake([
        'dummyjson.com/carts*' => Http::response(['carts' => [], 'total' => 0], 200),
    ]);

    Connector::factory()->create([
        'user_id' => null,
        'key' => 'dj_demo',
        'connector_type' => ConnectorType::DummyJson,
        'credentials' => [
            'base_url' => 'https://dummyjson.com',
        ],
    ]);

    $result = (new ConnectorsHelper(null))->dummyJson('dj_demo')->getOrders(['limit' => 2]);
    expect($result)->toMatchArray(['total' => 0, 'carts' => []]);
});

it('exposes Amazon Vendor Central getOrders with LWA and SigV4', function () {
    Http::fake(function (Request $request) {
        $url = $request->url();
        if (str_contains($url, 'api.amazon.com/auth/o2/token')) {
            return Http::response([
                'access_token' => 'lwa-test-token',
                'expires_in' => 3600,
                'token_type' => 'bearer',
            ], 200);
        }
        if (str_contains($url, 'sellingpartnerapi-eu.amazon.com') && str_contains($url, 'purchaseOrders')) {
            return Http::response(['orders' => [], 'pagination' => ['nextToken' => null]], 200);
        }

        return Http::response(['unexpected' => $url], 500);
    });

    Connector::factory()->create([
        'user_id' => null,
        'key' => 'amazon_vc_demo',
        'connector_type' => ConnectorType::AmazonVendorCentral,
        'credentials' => [
            'client_id' => 'amzn1.application-oa2-client.xxx',
            'client_secret' => 'secret',
            'refresh_token' => 'Atzr|IwEBI...',
            'aws_access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
            'aws_secret_access_key' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            'region' => 'eu-west-1',
            'sp_api_region' => 'eu',
        ],
    ]);

    $after = new DateTimeImmutable('2020-01-01T00:00:00Z');
    $before = new DateTimeImmutable('2020-01-02T00:00:00Z');

    $result = (new ConnectorsHelper(null))->amazonVendorCentral('amazon_vc_demo')->getOrders($after, $before);

    expect($result)->toMatchArray(['orders' => []]);

    Http::assertSent(fn ($r): bool => str_contains($r->url(), 'api.amazon.com/auth/o2/token'));
    Http::assertSent(fn ($r): bool => str_contains($r->url(), 'purchaseOrders'));
});

it('persists success when NetSuite connection test passes', function () {
    Http::fake([
        '*.suitetalk.api.netsuite.com/*' => Http::response(['items' => []], 200),
    ]);

    $connector = Connector::factory()->create([
        'user_id' => null,
        'key' => 'ns_test_ok',
        'connector_type' => ConnectorType::NetSuite,
        'credentials' => [
            'account_id' => '3521821_SB1',
            'client_id' => 'ck',
            'client_secret' => 'cs',
            'token_id' => 'tid',
            'token_secret' => 'ts',
            'restlet_url' => 'https://3521821-sb1.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=1&deploy=1',
        ],
    ]);

    $result = app(ConnectorConnectionTester::class)->test($connector);

    expect($result->success)->toBeTrue()
        ->and($connector->fresh()->last_connection_test_success)->toBeTrue()
        ->and($connector->fresh()->last_connection_test_error)->toBeNull();
});

it('persists failure when NetSuite connection test fails', function () {
    Http::fake([
        '*.suitetalk.api.netsuite.com/*' => Http::response(['title' => 'Unauthorized'], 401),
    ]);

    $connector = Connector::factory()->create([
        'user_id' => null,
        'key' => 'ns_test_fail',
        'connector_type' => ConnectorType::NetSuite,
        'credentials' => [
            'account_id' => '3521821_SB1',
            'client_id' => 'ck',
            'client_secret' => 'cs',
            'token_id' => 'tid',
            'token_secret' => 'ts',
            'restlet_url' => 'https://3521821-sb1.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=1&deploy=1',
        ],
    ]);

    $result = app(ConnectorConnectionTester::class)->test($connector);

    expect($result->success)->toBeFalse()
        ->and($connector->fresh()->last_connection_test_success)->toBeFalse()
        ->and($connector->fresh()->last_connection_test_error)->not->toBeNull();
});

it('does not persist when connector type does not support connection test', function () {
    Http::fake();

    $connector = Connector::factory()->create([
        'user_id' => null,
        'key' => 'amazon_no_test',
        'connector_type' => ConnectorType::AmazonVendorCentral,
        'credentials' => [
            'client_id' => 'x',
            'client_secret' => 'y',
            'refresh_token' => 'z',
            'aws_access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
            'aws_secret_access_key' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            'region' => 'eu-west-1',
            'sp_api_region' => 'eu',
        ],
    ]);

    $result = app(ConnectorConnectionTester::class)->test($connector);

    expect($result->success)->toBeFalse()
        ->and($connector->fresh()->last_connection_test_at)->toBeNull();
});

it('throws when resolving credentials for an unknown connector key', function () {
    expect(fn () => (new ConnectorsHelper(null))->credentials('does_not_exist'))
        ->toThrow(InvalidArgumentException::class, 'Integration connector [does_not_exist] not found');
});

it('throws when global connector exists but credentials are empty', function () {
    Connector::query()->create([
        'user_id' => null,
        'key' => 'empty_global',
        'name' => 'No creds',
        'connector_type' => ConnectorType::DummyJson,
        'credentials' => [],
    ]);

    expect(fn () => (new ConnectorsHelper(null))->credentials('empty_global'))
        ->toThrow(InvalidArgumentException::class, 'has no credentials configured');
});
