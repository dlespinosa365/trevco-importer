<?php

namespace App\Integrations\Contracts;

use App\Integrations\DiskFlowContext;
use App\Integrations\StepResult;

interface Step
{
    public function run(DiskFlowContext $context): StepResult;
}
