<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Emitters;

use UnitEnum;

final readonly class ZodEmitResult
{
    /**
     * @param  array<string, string>  $messages
     * @param  list<string>  $serverFields
     * @param  list<class-string<UnitEnum>>  $enumClasses
     * @param  list<string>  $superRefines
     * @param  array<string, list<string>>  $dependencies
     */
    public function __construct(
        public string $schema,
        public array $messages = [],
        public array $serverFields = [],
        public array $enumClasses = [],
        public array $superRefines = [],
        public array $dependencies = [],
    ) {}
}
