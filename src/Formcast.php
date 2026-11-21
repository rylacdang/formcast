<?php

declare(strict_types=1);

namespace Rylacdang\Formcast;

use Closure;

final class Formcast
{
    /**
     * Registered custom rule mappers keyed by rule name.
     *
     * @var array<string, Closure|class-string>
     */
    private array $mappers = [];

    /**
     * Register a custom client-side rule mapper.
     *
     * @param  Closure|class-string  $mapper
     */
    public function extend(string $rule, Closure|string $mapper): self
    {
        $this->mappers[strtolower($rule)] = $mapper;

        return $this;
    }

    /**
     * Retrieve a registered mapper by rule name.
     *
     * @return Closure|class-string|null
     */
    public function mapper(string $rule): Closure|string|null
    {
        return $this->mappers[strtolower($rule)] ?? null;
    }

    /**
     * Determine whether a custom mapper is registered for the given rule.
     */
    public function hasMapper(string $rule): bool
    {
        return array_key_exists(strtolower($rule), $this->mappers);
    }

    /**
     * Get all registered custom rule mappers.
     *
     * @return array<string, Closure|class-string>
     */
    public function mappers(): array
    {
        return $this->mappers;
    }

    /**
     * Clear all registered custom rule mappers.
     */
    public function flush(): void
    {
        $this->mappers = [];
    }
}
