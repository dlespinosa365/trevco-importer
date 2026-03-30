<?php

namespace App\Connectors\Contracts;

/**
 * Vendor HTTP client that can verify credentials with a minimal API call.
 */
interface TestsConnection
{
    /**
     * @throws \Throwable
     */
    public function testConnection(): void;
}
