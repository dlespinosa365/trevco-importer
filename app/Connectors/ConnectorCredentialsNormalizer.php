<?php

namespace App\Connectors;

use App\Enums\ConnectorType;

final class ConnectorCredentialsNormalizer
{
    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    public static function normalize(ConnectorType $type, array $credentials): array
    {
        $definition = ConnectorTypeRegistry::definition($type);
        $out = [];
        foreach ($definition->fields() as $field) {
            $v = $credentials[$field->name] ?? null;
            if (is_string($v)) {
                $v = trim($v);
            }
            $out[$field->name] = $v === '' ? null : $v;
        }

        return $out;
    }
}
