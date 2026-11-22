<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Extraction;

use Illuminate\Container\Container;
use Illuminate\Foundation\Http\FormRequest;
use ReflectionClass;
use RuntimeException;
use Rylacdang\Formcast\Discovery\DiscoveredRequest;
use Throwable;

final class FormRequestExtractor
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Extract rules, messages, and attributes from all discovered FormRequests.
     * Requests that throw during extraction are skipped with a warning message.
     *
     * @param  list<DiscoveredRequest>  $requests
     * @return array{extracted: list<ExtractedRequest>, skipped: array<class-string<FormRequest>, string>}
     */
    public function extractAll(array $requests, ?GenerationContext $context = null): array
    {
        $context ??= new GenerationContext;
        $extracted = [];
        $skipped = [];

        foreach ($requests as $discovered) {
            try {
                $extracted[] = $this->extract($discovered, $context);
            } catch (Throwable $exception) {
                $skipped[$discovered->class] = sprintf(
                    'Skipped %s: %s',
                    $discovered->class,
                    $exception->getMessage() !== '' ? $exception->getMessage() : $exception::class,
                );
            }
        }

        return [
            'extracted' => $extracted,
            'skipped' => $skipped,
        ];
    }

    /**
     * Extract a single FormRequest without triggering validation or authorization.
     */
    public function extract(DiscoveredRequest $discovered, ?GenerationContext $context = null): ExtractedRequest
    {
        $context ??= new GenerationContext;
        $request = $this->instantiate($discovered->class);

        $request->setContainer($this->container);
        $context->apply($request);

        $usedFormcastRules = method_exists($request, 'formcastRules');
        $rulesMethod = $usedFormcastRules ? 'formcastRules' : 'rules';

        $callback = [$request, $rulesMethod];

        if (! is_callable($callback)) {
            throw new RuntimeException("FormRequest {$discovered->class} does not define a callable {$rulesMethod}() method.");
        }

        $rawRules = $this->container->call($callback);

        if (! is_array($rawRules)) {
            throw new RuntimeException("Method {$discovered->class}::{$rulesMethod}() must return an array.");
        }

        /** @var array<string, mixed> $rules */
        $rules = $rawRules;

        $rawMessages = $this->container->call([$request, 'messages']);
        $rawAttributes = $this->container->call([$request, 'attributes']);

        /** @var array<string, string> $messages */
        $messages = is_array($rawMessages)
            ? array_filter($rawMessages, is_string(...))
            : [];

        /** @var array<string, string> $attributes */
        $attributes = is_array($rawAttributes)
            ? array_filter($rawAttributes, is_string(...))
            : [];

        return new ExtractedRequest(
            class: $discovered->class,
            exportName: $discovered->exportName,
            sourcePath: $discovered->filePath,
            rules: $rules,
            messages: $messages,
            attributes: $attributes,
            hasPrepareForValidation: $this->overridesPrepareForValidation($discovered->class),
            usedFormcastRules: $usedFormcastRules,
        );
    }

    /**
     * Instantiate a FormRequest without invoking container afterResolving(ValidatesWhenResolved).
     *
     * @param  class-string<FormRequest>  $class
     */
    private function instantiate(string $class): FormRequest
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getDeclaringClass()->getName() !== $class) {
            /** @var FormRequest $instance */
            $instance = $class::create('/');

            return $instance;
        }

        /** @var FormRequest $instance */
        $instance = $reflection->newInstanceWithoutConstructor();

        return $instance;
    }

    /**
     * @param  class-string<FormRequest>  $class
     */
    private function overridesPrepareForValidation(string $class): bool
    {
        $reflection = new ReflectionClass($class);

        if (! $reflection->hasMethod('prepareForValidation')) {
            return false;
        }

        $method = $reflection->getMethod('prepareForValidation');

        return $method->getDeclaringClass()->getName() !== FormRequest::class;
    }
}
