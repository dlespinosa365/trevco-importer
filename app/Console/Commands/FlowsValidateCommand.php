<?php

namespace App\Console\Commands;

use App\Integrations\FlowDefinitionRegistry;
use Illuminate\Console\Command;

class FlowsValidateCommand extends Command
{
    protected $signature = 'flows:validate';

    protected $description = 'Validate disk flow definitions (integration/group/flow); exits non-zero on error.';

    public function handle(FlowDefinitionRegistry $registry): int
    {
        $refs = $registry->allFlowRefs();

        if ($refs === []) {
            $this->warn('No flow definitions found under '.config('flows.integrations_path').'.');

            return self::SUCCESS;
        }

        $errors = [];

        foreach ($refs as $ref) {
            try {
                $registry->resolve($ref);
            } catch (\Throwable $e) {
                $errors[] = "[{$ref}] ".$e->getMessage();
            }
        }

        if ($errors !== []) {
            foreach ($errors as $line) {
                $this->error($line);
            }

            return self::FAILURE;
        }

        $this->info('All '.count($refs).' flow definition(s) are valid.');

        return self::SUCCESS;
    }
}
