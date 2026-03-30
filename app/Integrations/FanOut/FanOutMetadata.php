<?php

namespace App\Integrations\FanOut;

use App\Integrations\Attributes\FanOut as FanOutAttribute;
use ReflectionClass;

final class FanOutMetadata
{
    public static function resolve(string $stepClass): ?FanOutConfig
    {
        if (! class_exists($stepClass)) {
            return null;
        }

        $reflection = new ReflectionClass($stepClass);

        foreach ($reflection->getAttributes(FanOutAttribute::class) as $attribute) {
            $instance = $attribute->newInstance();

            return new FanOutConfig(
                itemsPath: $instance->itemsPath,
                itemReferenceKey: $instance->itemReferenceKey,
                enabled: $instance->enabled,
            );
        }

        return null;
    }
}
