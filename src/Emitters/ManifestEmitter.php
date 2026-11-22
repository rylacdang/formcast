<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Emitters;

final class ManifestEmitter
{
    /**
     * Emit the `defineCast(...)` manifest block with deterministic ordering.
     *
     * @param  list<string>  $serverFields
     * @param  array<string, list<string>>  $dependencies
     * @param  list<array{action: string, methods: list<string>, uri: string, name?: ?string}>  $usedBy
     */
    public function emit(
        string $exportName,
        array $serverFields = [],
        array $dependencies = [],
        array $usedBy = [],
        string $schemaVariable = 'schema',
    ): string {
        $sortedServerFields = array_values(array_unique($serverFields));
        sort($sortedServerFields, SORT_STRING);

        ksort($dependencies, SORT_STRING);
        $normalizedDeps = [];

        foreach ($dependencies as $trigger => $dependents) {
            $unique = array_values(array_unique($dependents));
            sort($unique, SORT_STRING);
            $normalizedDeps[$trigger] = $unique;
        }

        usort(
            $usedBy,
            static fn (array $a, array $b): int => ($a['action'] <=> $b['action']) ?: ($a['uri'] <=> $b['uri']),
        );

        $lines = [
            "export const {$exportName} = defineCast({",
            '  name: '.$this->quote($exportName).',',
            "  {$schemaVariable},",
            '  serverFields: '.$this->formatStringList($sortedServerFields).',',
        ];

        if ($normalizedDeps === []) {
            $lines[] = '  dependencies: {},';
        } else {
            $lines[] = '  dependencies: {';

            foreach ($normalizedDeps as $trigger => $dependents) {
                $lines[] = '    '.$this->formatPropertyKey($trigger).': '.$this->formatStringList($dependents).',';
            }

            $lines[] = '  },';
        }

        if ($usedBy === []) {
            $lines[] = '  usedBy: [],';
        } else {
            $lines[] = '  usedBy: [';

            foreach ($usedBy as $route) {
                $parts = [
                    'action: '.$this->quote($route['action']),
                    'methods: '.$this->formatStringList($route['methods']),
                    'uri: '.$this->quote($route['uri']),
                ];

                if (isset($route['name']) && $route['name'] !== '') {
                    $parts[] = 'name: '.$this->quote($route['name']);
                }

                $lines[] = '    { '.implode(', ', $parts).' },';
            }

            $lines[] = '  ],';
        }

        $lines[] = '});';

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $items
     */
    private function formatStringList(array $items): string
    {
        if ($items === []) {
            return '[]';
        }

        return '['.implode(', ', array_map($this->quote(...), $items)).']';
    }

    private function formatPropertyKey(string $key): string
    {
        if (preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $key) === 1) {
            return $key;
        }

        return $this->quote($key);
    }

    private function quote(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }
}
