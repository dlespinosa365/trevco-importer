<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

it('scaffolds an integration with interactive answers', function () {
    $slug = 'zmake'.fake()->numerify('####');
    $base = base_path('integrations/'.$slug);
    $studlyIntegration = Str::studly(str_replace('-', '_', $slug));
    $stepClass = $studlyIntegration.'EntryStep';

    if (File::isDirectory($base)) {
        File::deleteDirectory($base);
    }

    $this->artisan('integrations:make')
        ->expectsQuestion('Integration folder slug (kebab-case, e.g. shopify)', $slug)
        ->expectsQuestion('Integration key', $slug)
        ->expectsQuestion('Integration display name', Str::title(str_replace('-', ' ', $slug)))
        ->expectsQuestion('Group slug (kebab-case, default: default)', 'default')
        ->expectsQuestion('Flow slug (kebab-case, default: main)', 'main')
        ->expectsConfirmation('Generate an entry Step class inside the integration folder?', 'yes')
        ->expectsQuestion('Step class name', $stepClass)
        ->assertExitCode(0);

    expect(File::exists($base.'/config.php'))->toBeTrue();
    expect(File::exists($base.'/groups/default/config.php'))->toBeTrue();
    expect(File::exists($base.'/groups/default/flows/main/flow.php'))->toBeTrue();
    expect(File::exists($base.'/groups/default/flows/main/'.$stepClass.'.php'))->toBeTrue();

    File::deleteDirectory($base);
});
