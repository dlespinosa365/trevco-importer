<?php

namespace App\Support;

/**
 * Splits merged flow step context and run snapshots into labeled sections for operator-facing UIs.
 */
final class HumanReadablePayloadSections
{
    /**
     * @return list<array{heading: string, body: string}>
     */
    public static function from(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [['heading' => 'Value', 'body' => self::encode($payload)]];
        }

        if ($payload === []) {
            return [['heading' => 'Payload', 'body' => '{}']];
        }

        if (self::isRunLevelWrapper($payload)) {
            return [
                ['heading' => 'Trigger payload', 'body' => self::encode($payload['trigger_payload'] ?? [])],
                ['heading' => 'Execution context', 'body' => self::encode($payload['context'] ?? [])],
            ];
        }

        return self::fromMergedContext($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function isRunLevelWrapper(array $data): bool
    {
        $keys = array_keys($data);
        sort($keys);

        return $keys === ['context', 'trigger_payload']
            || $keys === ['trigger_payload', 'context'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{heading: string, body: string}>
     */
    private static function fromMergedContext(array $data): array
    {
        $sections = [];
        $remaining = $data;

        $meta = [];
        if (array_key_exists('_fan_out_reference', $remaining)) {
            $meta['_fan_out_reference'] = $remaining['_fan_out_reference'];
            unset($remaining['_fan_out_reference']);
        }
        if (array_key_exists('source', $remaining)) {
            $meta['source'] = $remaining['source'];
            unset($remaining['source']);
        }
        if ($meta !== []) {
            $sections[] = ['heading' => 'Context', 'body' => self::encode($meta)];
        }

        if (array_key_exists('_fan_out_item', $remaining)) {
            $sections[] = ['heading' => 'Current fan-out item', 'body' => self::encode($remaining['_fan_out_item'])];
            unset($remaining['_fan_out_item']);
        }

        $stepEntries = [];
        foreach ($remaining as $key => $value) {
            if (preg_match('/^(\d+)_(.+)$/', (string) $key, $matches)) {
                $stepEntries[$key] = [
                    'index' => (int) $matches[1],
                    'class' => $matches[2],
                    'value' => $value,
                ];
            }
        }

        uasort($stepEntries, fn (array $a, array $b): int => $a['index'] <=> $b['index']);

        foreach ($stepEntries as $key => $entry) {
            unset($remaining[$key]);
            $heading = sprintf(
                'Step %d · %s',
                $entry['index'],
                self::humanizeClassBasename($entry['class'])
            );
            $sections[] = ['heading' => $heading, 'body' => self::encode($entry['value'])];
        }

        if ($remaining !== []) {
            $sections[] = ['heading' => 'Other', 'body' => self::encode($remaining)];
        }

        return $sections;
    }

    private static function humanizeClassBasename(string $basename): string
    {
        $withSpaces = preg_replace('/([a-z])([A-Z])/', '$1 $2', $basename);

        return ($withSpaces !== null && $withSpaces !== '') ? $withSpaces : $basename;
    }

    private static function encode(mixed $data): string
    {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return is_string($json) ? $json : '{}';
    }
}
