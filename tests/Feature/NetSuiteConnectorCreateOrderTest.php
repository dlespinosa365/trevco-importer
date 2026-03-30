<?php

use App\Connectors\Vendors\NetSuite\NetSuiteConnector;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('does not send isDynamic on createTransaction; RESTlet uses standard record mode', function (): void {
    Http::fake(function (Request $request) {
        return Http::response(json_encode([
            'ok' => true,
            'action' => 'createTransaction',
            'id' => '99',
        ], JSON_THROW_ON_ERROR), 200);
    });

    $connector = new NetSuiteConnector([
        'account_id' => '3521821_SB1',
        'client_id' => 'ck',
        'client_secret' => 'cs',
        'token_id' => 'ti',
        'token_secret' => 'ts',
        'restlet_url' => 'https://3521821-sb1.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=1&deploy=1',
    ]);

    $connector->createOrder([
        'entity' => ['id' => '1'],
        'item' => ['items' => [['item' => ['id' => '2'], 'quantity' => 1]]],
    ]);

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'restlet.nl')) {
            return false;
        }
        $json = json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR);

        return ! array_key_exists('isDynamic', $json)
            && $json['action'] === 'createTransaction';
    });
});

it('strips isDynamic from bodyParams so callers cannot override RESTlet create mode', function (): void {
    Http::fake(function (Request $request) {
        return Http::response(json_encode([
            'ok' => true,
            'action' => 'createTransaction',
            'id' => '1',
        ], JSON_THROW_ON_ERROR), 200);
    });

    $connector = new NetSuiteConnector([
        'account_id' => '3521821_SB1',
        'client_id' => 'ck',
        'client_secret' => 'cs',
        'token_id' => 'ti',
        'token_secret' => 'ts',
        'restlet_url' => 'https://3521821-sb1.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=1&deploy=1',
    ]);

    $connector->createOrder([
        'bodyParams' => [
            'entity' => '95311',
            'isDynamic' => true,
        ],
        'lineParams' => [
            ['item' => '13527919', 'quantity' => 1],
        ],
    ]);

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'restlet.nl')) {
            return false;
        }
        $json = json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR);

        return ! array_key_exists('isDynamic', $json['bodyParams'] ?? []);
    });
});
