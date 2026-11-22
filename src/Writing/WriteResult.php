<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Writing;

final readonly class WriteResult
{
    /**
     * @param  list<string>  $written
     * @param  list<string>  $unchanged
     * @param  list<string>  $removed
     */
    public function __construct(
        public array $written = [],
        public array $unchanged = [],
        public array $removed = [],
    ) {}

    public function hasChanges(): bool
    {
        return $this->written !== [] || $this->removed !== [];
    }
}
