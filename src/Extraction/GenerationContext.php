<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Extraction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;

final readonly class GenerationContext
{
    /**
     * @param  array<string, mixed>  $routeParameters
     */
    public function __construct(
        public mixed $user = null,
        public array $routeParameters = [],
    ) {}

    public function apply(FormRequest $request): void
    {
        $user = $this->user;
        $request->setUserResolver(static fn (): mixed => $user);

        $route = new Route(['GET'], '/', static fn (): null => null);
        $route->bind($request);

        foreach ($this->routeParameters as $key => $value) {
            $route->setParameter($key, $value);
        }

        $request->setRouteResolver(static fn (): Route => $route);
    }
}
