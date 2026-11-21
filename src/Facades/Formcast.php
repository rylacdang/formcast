<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Rylacdang\Formcast\Formcast as FormcastRegistry;

/**
 * @method static FormcastRegistry extend(string $rule, Closure|string $mapper)
 * @method static Closure|string|null mapper(string $rule)
 * @method static bool hasMapper(string $rule)
 * @method static array<string, Closure|string> mappers()
 * @method static void flush()
 *
 * @see FormcastRegistry
 */
final class Formcast extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FormcastRegistry::class;
    }
}
