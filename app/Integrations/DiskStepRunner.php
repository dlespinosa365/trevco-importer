<?php

namespace App\Integrations;

use App\Integrations\Contracts\Step;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class DiskStepRunner
{
    public function __construct(private Container $container) {}

    public function run(string $stepClass, DiskFlowContext $context): StepResult
    {
        if (! class_exists($stepClass)) {
            throw new InvalidArgumentException("Step class does not exist: {$stepClass}");
        }

        if (! is_subclass_of($stepClass, Step::class)) {
            throw new InvalidArgumentException('Step class must implement '.Step::class.": {$stepClass}");
        }

        /** @var Step $step */
        $step = $this->container->make($stepClass);

        return $step->run($context);
    }
}
