<?php

namespace App\Integrations;

final class StepLogCollector
{
    /** @var list<array{level: string, message: string, context?: array<string, mixed>}> */
    private array $entries = [];

    public function info(string $message, array $context = []): void
    {
        $this->entries[] = ['level' => 'info', 'message' => $message, 'context' => $context];
    }

    public function warning(string $message, array $context = []): void
    {
        $this->entries[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
    }

    public function error(string $message, array $context = []): void
    {
        $this->entries[] = ['level' => 'error', 'message' => $message, 'context' => $context];
    }

    /**
     * @return list<array{level: string, message: string, context?: array<string, mixed>}>
     */
    public function toArray(): array
    {
        return $this->entries;
    }
}
