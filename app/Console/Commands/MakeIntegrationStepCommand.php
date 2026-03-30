<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeIntegrationStepCommand extends Command
{
    protected $signature = 'integrations:make-step';

    protected $description = 'Create a step class under integrations/{integration}/groups/{group}/flows/{flow}.';

    public function handle(): int
    {
        $basePath = rtrim((string) config('flows.integrations_path', base_path('integrations')), DIRECTORY_SEPARATOR);

        $integrationSlug = $this->askKebabSlug('Integration slug');
        $groupSlug = $this->askKebabSlug('Group slug');
        $flowSlug = $this->askKebabSlug('Flow slug');
        $defaultClass = Str::studly(str_replace('-', '_', $flowSlug)).'Step';
        $className = $this->askPascalCase('Step class name', $defaultClass);

        $flowDir = $basePath
            .DIRECTORY_SEPARATOR.$integrationSlug
            .DIRECTORY_SEPARATOR.'groups'
            .DIRECTORY_SEPARATOR.$groupSlug
            .DIRECTORY_SEPARATOR.'flows'
            .DIRECTORY_SEPARATOR.$flowSlug;

        File::ensureDirectoryExists($flowDir);

        $stepPath = $flowDir.DIRECTORY_SEPARATOR.$className.'.php';
        if (File::exists($stepPath) && ! $this->confirm("Step file [{$stepPath}] already exists. Overwrite?", false)) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $namespace = 'Integrations\\'
            .Str::studly(str_replace('-', '_', $integrationSlug))
            .'\\Groups\\'
            .Str::studly(str_replace('-', '_', $groupSlug))
            .'\\Flows\\'
            .Str::studly(str_replace('-', '_', $flowSlug));

        File::put($stepPath, $this->stepClassContents($namespace, $className));
        $this->info("Step created: {$integrationSlug}/{$groupSlug}/{$flowSlug}/{$className}");

        $flowPath = $flowDir.DIRECTORY_SEPARATOR.'flow.php';
        if (! File::exists($flowPath)) {
            File::put($flowPath, $this->flowConfigContents($namespace.'\\'.$className));
            $this->info("Flow config created: {$integrationSlug}/{$groupSlug}/{$flowSlug}");
        }

        return self::SUCCESS;
    }

    private function askKebabSlug(string $question): string
    {
        while (true) {
            $value = $this->ask($question);
            $value = is_string($value) ? trim($value) : '';

            if ($value === '') {
                $this->error('Value is required.');

                continue;
            }

            if (! preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $value)) {
                $this->error('Use kebab-case: lowercase letters, numbers, and hyphens.');

                continue;
            }

            return $value;
        }
    }

    private function askPascalCase(string $question, string $default): string
    {
        while (true) {
            $value = $this->ask($question, $default);
            $value = is_string($value) ? trim($value) : '';

            if ($value === '') {
                $this->error('Class name is required.');

                continue;
            }

            if (! preg_match('/^[A-Z][a-zA-Z0-9]*$/', $value)) {
                $this->error('Use PascalCase for class names.');

                continue;
            }

            return $value;
        }
    }

    private function stepClassContents(string $namespace, string $className): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use App\Integrations\Contracts\Step;
use App\Integrations\DiskFlowContext;
use App\Integrations\StepResult;

final class {$className} implements Step
{
    public function run(DiskFlowContext \$context): StepResult
    {
        \$context->logs->info('Step executed: {$className}');

        return new StepResult(
            ['status' => 'ok'],
            null,
        );
    }
}

PHP;
    }

    private function flowConfigContents(string $entryFqcn): string
    {
        $entryShort = Str::afterLast($entryFqcn, '\\');
        $useStatement = "use {$entryFqcn};";

        return <<<PHP
<?php

{$useStatement}

return [
    'entry' => [
        {$entryShort}::class,
    ],
];

PHP;
    }
}
