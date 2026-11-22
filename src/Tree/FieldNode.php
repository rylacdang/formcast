<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Tree;

use Rylacdang\Formcast\Rules\NormalizedRule;

final readonly class FieldNode
{
    /**
     * @param  list<NormalizedRule>  $rules
     * @param  array<string, self>  $children
     */
    public function __construct(
        public string $name,
        public string $path,
        public array $rules = [],
        public array $children = [],
        public ?self $element = null,
        public bool $implicit = false,
    ) {}

    public function isObject(): bool
    {
        return $this->children !== [];
    }

    public function isArray(): bool
    {
        return $this->element !== null;
    }

    public function isLeaf(): bool
    {
        return $this->children === [] && $this->element === null;
    }

    public function child(string $name): ?self
    {
        return $this->children[$name] ?? null;
    }
}
