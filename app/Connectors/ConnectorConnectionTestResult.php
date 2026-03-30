<?php

namespace App\Connectors;

final class ConnectorConnectionTestResult
{
    public function __construct(
        public bool $success,
        public ?string $message = null,
    ) {}
}
