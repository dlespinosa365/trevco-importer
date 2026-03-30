<?php

use App\Enums\ConnectorType;
use App\Models\Connector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates a group scaffold inside an existing integration', function () {
    $integrationSlug = 'zgroup'.fake()->numerify('####');
    $groupSlug = 'orders';
    $integrationDir = base_path('integrations/'.$integrationSlug);

    File::ensureDirectoryExists($integrationDir);
    File::put($integrationDir.'/config.php', <<<'PHP'
<?php

return [
    'key' => 'tmp',
    'name' => 'Tmp',
    'image_url' => null,
    'extra_config' => [],
    'failure_notifications' => [
        'mail' => [],
        'slack_webhook_url' => null,
        'teams_workflow_webhook_url' => null,
    ],
];
PHP);

    $this->artisan('integrations:make-group')
        ->expectsQuestion('Integration slug (existing folder)', $integrationSlug)
        ->expectsQuestion('Group slug', $groupSlug)
        ->assertExitCode(0);

    expect(File::exists($integrationDir.'/groups/'.$groupSlug.'/config.php'))->toBeTrue()
        ->and(File::isDirectory($integrationDir.'/groups/'.$groupSlug.'/flows'))->toBeTrue();

    File::deleteDirectory($integrationDir);
});

it('creates a step class and flow config when missing', function () {
    $integrationSlug = 'zstep'.fake()->numerify('####');
    $groupSlug = 'orders';
    $flowSlug = 'sync-orders';
    $stepClass = 'FetchOrdersStep';
    $integrationDir = base_path('integrations/'.$integrationSlug);

    File::ensureDirectoryExists($integrationDir);

    $this->artisan('integrations:make-step')
        ->expectsQuestion('Integration slug', $integrationSlug)
        ->expectsQuestion('Group slug', $groupSlug)
        ->expectsQuestion('Flow slug', $flowSlug)
        ->expectsQuestion('Step class name', $stepClass)
        ->assertExitCode(0);

    $stepPath = $integrationDir.'/groups/'.$groupSlug.'/flows/'.$flowSlug.'/'.$stepClass.'.php';
    $flowPath = $integrationDir.'/groups/'.$groupSlug.'/flows/'.$flowSlug.'/flow.php';

    expect(File::exists($stepPath))->toBeTrue()
        ->and(File::exists($flowPath))->toBeTrue();

    $stepContents = File::get($stepPath);
    expect($stepContents)->toContain('namespace Integrations\\'.Str::studly(str_replace('-', '_', $integrationSlug)))
        ->and($stepContents)->toContain('final class '.$stepClass.' implements Step');

    File::deleteDirectory($integrationDir);
});

it('creates a connector record with minimal dummy json credentials', function () {
    $this->artisan('connectors:make')
        ->expectsQuestion('Connector key', 'dummy_cmd_key')
        ->expectsQuestion('Connector display name', 'Dummy Connector')
        ->expectsQuestion('Connector type', 'dummy_json (Dummy JSON)')
        ->expectsQuestion('Owner user id (leave empty for global)', '')
        ->expectsQuestion('Dummy JSON base_url', 'https://dummyjson.com')
        ->assertExitCode(0);

    $connector = Connector::query()->where('key', 'dummy_cmd_key')->first();
    expect($connector)->not->toBeNull()
        ->and($connector->connector_type)->toBe(ConnectorType::DummyJson)
        ->and($connector->credentials)->toMatchArray([
            'base_url' => 'https://dummyjson.com',
        ]);
});
