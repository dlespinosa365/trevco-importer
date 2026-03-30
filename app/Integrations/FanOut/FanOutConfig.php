<?php

namespace App\Integrations\FanOut;

final class FanOutConfig
{
    public function __construct(
        public string $itemsPath,
        public string $itemReferenceKey,
        public bool $enabled = true,
    ) {}
}
