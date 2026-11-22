<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Rules;

final readonly class NormalizedRule
{
    /**
     * @param  list<string>  $parameters
     */
    public function __construct(
        public string $name,
        public array $parameters = [],
        public mixed $source = null,
        public bool $forceServer = false,
    ) {}
}
