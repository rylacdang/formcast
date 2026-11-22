<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Emitters;

final class TypeEmitter
{
    /**
     * Emit z.input and z.output TypeScript type aliases for a cast schema.
     */
    public function emit(string $exportName, string $schemaVariable = 'schema'): string
    {
        return implode("\n", [
            "export type {$exportName}Input = z.input<typeof {$schemaVariable}>;",
            "export type {$exportName}Output = z.output<typeof {$schemaVariable}>;",
        ]);
    }
}
