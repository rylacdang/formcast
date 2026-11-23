<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Discovery;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Router;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

final class RouteScanner
{
    public function __construct(
        private readonly Router $router,
    ) {}

    /**
     * Scan registered routes and map each FormRequest FQCN to the controller actions using it.
     *
     * @return array<class-string<FormRequest>, list<array{action: string, methods: list<string>, uri: string, name: ?string}>>
     */
    public function scan(): array
    {
        /** @var array<class-string<FormRequest>, list<array{action: string, methods: list<string>, uri: string, name: ?string}>> $map */
        $map = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if ($route->getAction('uses') instanceof Closure) {
                continue;
            }

            $actionName = $route->getActionName();

            if ($actionName === '' || $actionName === 'Closure') {
                continue;
            }

            [$controller, $method] = $this->parseAction($actionName);

            if (! class_exists($controller) || ! method_exists($controller, $method)) {
                continue;
            }

            $requestClasses = $this->extractFormRequestTypes(new ReflectionMethod($controller, $method));

            if ($requestClasses === []) {
                continue;
            }

            $entry = [
                'action' => $method === '__invoke' ? $controller : $controller.'@'.$method,
                'methods' => $this->normalizeMethods($route->methods()),
                'uri' => '/'.ltrim($route->uri(), '/'),
                'name' => $route->getName(),
            ];

            foreach ($requestClasses as $requestClass) {
                $map[$requestClass] ??= [];
                $map[$requestClass][] = $entry;
            }
        }

        ksort($map, SORT_STRING);

        foreach ($map as $requestClass => $entries) {
            usort(
                $entries,
                static fn (array $a, array $b): int => ($a['action'] <=> $b['action']) ?: ($a['uri'] <=> $b['uri']),
            );
            $map[$requestClass] = $entries;
        }

        return $map;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseAction(string $actionName): array
    {
        if (str_contains($actionName, '@')) {
            [$controller, $method] = explode('@', $actionName, 2);

            return [$controller, $method];
        }

        return [$actionName, '__invoke'];
    }

    /**
     * @return list<class-string<FormRequest>>
     */
    private function extractFormRequestTypes(ReflectionMethod $method): array
    {
        /** @var list<class-string<FormRequest>> $types */
        $types = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            $namedTypes = match (true) {
                $type instanceof ReflectionNamedType => [$type],
                $type instanceof ReflectionUnionType => array_values(array_filter(
                    $type->getTypes(),
                    static fn (object $t): bool => $t instanceof ReflectionNamedType,
                )),
                default => [],
            };

            foreach ($namedTypes as $namedType) {
                if ($namedType->isBuiltin()) {
                    continue;
                }

                $className = $namedType->getName();

                if (class_exists($className) && is_subclass_of($className, FormRequest::class)) {
                    $types[] = $className;
                }
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * @param  array<int, string>  $methods
     * @return list<string>
     */
    private function normalizeMethods(array $methods): array
    {
        $filtered = array_values(array_filter(
            $methods,
            static fn (string $method): bool => $method !== 'HEAD' || ! in_array('GET', $methods, true),
        ));

        return $filtered;
    }
}
