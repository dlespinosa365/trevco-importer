<?php

use App\Connectors\Vendors\NetSuite\NetSuiteConnector;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('includes the full HTTP error body in NetSuite request exceptions', function () {
    $unique = 'NETSUITE_FULL_BODY_TAIL_'.str_repeat('x', 100);

    Http::fake([
        '*suitetalk.api.netsuite.com/services/rest/query/v1/suiteql' => Http::response(
            json_encode(['o' => ['k' => $unique]], JSON_THROW_ON_ERROR),
            422
        ),
    ]);

    $connector = new NetSuiteConnector([
        'account_id' => '3521821_SB1',
        'client_id' => 'ck',
        'client_secret' => 'cs',
        'token_id' => 'ti',
        'token_secret' => 'ts',
    ]);

    try {
        $connector->executeQuery('SELECT 1');
        expect(false)->toBeTrue('Expected RequestException to be thrown.');
    } catch (RequestException $e) {
        expect($e->getMessage())->toContain($unique);
    }
});
