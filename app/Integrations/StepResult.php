<?php

namespace App\Integrations;

final class StepResult
{
    public function __construct(
        public array $output,
        public ?string $nextStepClass = null,
    ) {}
}
