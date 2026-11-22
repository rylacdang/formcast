<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Emitters;

use Closure;
use Rylacdang\Formcast\Contracts\CastsToZod;
use Rylacdang\Formcast\Contracts\RuleMapper;
use Rylacdang\Formcast\Emitters\Mappers\CrossFieldMappers;
use Rylacdang\Formcast\Emitters\Mappers\DateMappers;
use Rylacdang\Formcast\Emitters\Mappers\FileMappers;
use Rylacdang\Formcast\Emitters\Mappers\FormatMappers;
use Rylacdang\Formcast\Emitters\Mappers\PasswordMapper;
use Rylacdang\Formcast\Emitters\Mappers\PresenceMappers;
use Rylacdang\Formcast\Emitters\Mappers\SetMappers;
use Rylacdang\Formcast\Emitters\Mappers\SizeMappers;
use Rylacdang\Formcast\Emitters\Mappers\TypeMappers;
use Rylacdang\Formcast\Formcast;
use Rylacdang\Formcast\Messages\MessageResolver;
use Rylacdang\Formcast\Rules\FieldType;
use Rylacdang\Formcast\Rules\NormalizedRule;
use Rylacdang\Formcast\Rules\RuleCategory;
use Rylacdang\Formcast\Rules\RuleClassifier;
use Rylacdang\Formcast\Rules\TypeInferrer;
use Rylacdang\Formcast\Tree\FieldNode;
use UnitEnum;

final class ZodEmitter
{
    /** @var array<string, RuleMapper> */
    private array $mappers = [];

    /**
     * @param  array<string, class-string<RuleMapper>>  $configMappers
     */
    public function __construct(
        private readonly MessageResolver $messageResolver,
        private readonly RuleClassifier $classifier = new RuleClassifier,
        private readonly TypeInferrer $typeInferrer = new TypeInferrer,
        private readonly ?Formcast $registry = null,
        array $configMappers = [],
    ) {
        $this->registerDefaultMappers();

        foreach ($configMappers as $ruleName => $mapperClass) {
            $this->mappers[strtolower($ruleName)] = new $mapperClass;
        }
    }

    /**
     * Emit a deterministic Zod schema and collected metadata from a FieldNode tree.
     *
     * @param  array<string, string>  $customMessages
     * @param  array<string, string>  $customAttributes
     */
    public function emit(
        FieldNode $root,
        array $customMessages = [],
        array $customAttributes = [],
        ?string $locale = null,
    ): ZodEmitResult {
        /** @var array<string, string> $messages */
        $messages = [];

        /** @var list<string> $serverFields */
        $serverFields = [];

        /** @var list<class-string<UnitEnum>> $enumClasses */
        $enumClasses = [];

        /** @var list<string> $superRefines */
        $superRefines = [];

        /** @var array<string, list<string>> $dependencies */
        $dependencies = [];

        $schema = $this->emitObjectBody(
            node: $root,
            indent: 0,
            customMessages: $customMessages,
            customAttributes: $customAttributes,
            locale: $locale,
            messages: $messages,
            serverFields: $serverFields,
            enumClasses: $enumClasses,
            superRefines: $superRefines,
            dependencies: $dependencies,
        );

        foreach ($superRefines as $refine) {
            $schema .= "\n  .superRefine(".$refine.')';
        }

        ksort($messages, SORT_STRING);

        $serverFields = array_values(array_unique($serverFields));
        sort($serverFields, SORT_STRING);

        $enumClasses = array_values(array_unique($enumClasses));
        sort($enumClasses, SORT_STRING);

        ksort($dependencies, SORT_STRING);

        foreach ($dependencies as $trigger => $dependents) {
            $unique = array_values(array_unique($dependents));
            sort($unique, SORT_STRING);
            $dependencies[$trigger] = $unique;
        }

        return new ZodEmitResult(
            schema: $schema,
            messages: $messages,
            serverFields: $serverFields,
            enumClasses: $enumClasses,
            superRefines: $superRefines,
            dependencies: $dependencies,
        );
    }

    /**
     * @param  array<string, string>  $customMessages
     * @param  array<string, string>  $customAttributes
     * @param  array<string, string>  $messages
     * @param  list<string>  $serverFields
     * @param  list<class-string<UnitEnum>>  $enumClasses
     * @param  list<string>  $superRefines
     * @param  array<string, list<string>>  $dependencies
     */
    private function emitObjectBody(
        FieldNode $node,
        int $indent,
        array $customMessages,
        array $customAttributes,
        ?string $locale,
        array &$messages,
        array &$serverFields,
        array &$enumClasses,
        array &$superRefines,
        array &$dependencies,
    ): string {
        if ($node->children === []) {
            return 'z.object({})';
        }

        $pad = str_repeat('  ', $indent);
        $innerPad = str_repeat('  ', $indent + 1);

        /** @var array<string, string> $properties */
        $properties = [];

        /** @var list<string> $confirmationFields */
        $confirmationFields = [];

        foreach ($node->children as $key => $child) {
            $properties[$key] = $this->emitNode(
                node: $child,
                indent: $indent + 1,
                customMessages: $customMessages,
                customAttributes: $customAttributes,
                locale: $locale,
                messages: $messages,
                serverFields: $serverFields,
                enumClasses: $enumClasses,
                superRefines: $superRefines,
                dependencies: $dependencies,
                confirmationFields: $confirmationFields,
            );
        }

        foreach ($confirmationFields as $confirmPath) {
            $confirmKey = $this->localConfirmationKey($node->path, $confirmPath);

            if ($confirmKey !== null && ! array_key_exists($confirmKey, $properties)) {
                $properties[$confirmKey] = 'fc.optional(fc.string())';
            }
        }

        ksort($properties, SORT_STRING);

        $lines = ['z.object({'];

        foreach ($properties as $key => $expr) {
            $formattedKey = $this->formatPropertyKey($key);
            $lines[] = $innerPad.$formattedKey.': '.$expr.',';
        }

        $lines[] = $pad.'})';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, string>  $customMessages
     * @param  array<string, string>  $customAttributes
     * @param  array<string, string>  $messages
     * @param  list<string>  $serverFields
     * @param  list<class-string<UnitEnum>>  $enumClasses
     * @param  list<string>  $superRefines
     * @param  array<string, list<string>>  $dependencies
     * @param  list<string>  $confirmationFields
     */
    private function emitNode(
        FieldNode $node,
        int $indent,
        array $customMessages,
        array $customAttributes,
        ?string $locale,
        array &$messages,
        array &$serverFields,
        array &$enumClasses,
        array &$superRefines,
        array &$dependencies,
        array &$confirmationFields,
    ): string {
        $fieldType = $this->inferNodeType($node);

        $ctx = new ZodContext(
            field: $node->path,
            fieldType: $fieldType,
            messageResolver: $this->messageResolver,
            customMessages: $customMessages,
            customAttributes: $customAttributes,
            locale: $locale,
        );

        if ($node->isArray() && $node->element !== null) {
            $elementConfirmations = [];
            $elementExpr = $this->emitNode(
                node: $node->element,
                indent: $indent + 1,
                customMessages: $customMessages,
                customAttributes: $customAttributes,
                locale: $locale,
                messages: $messages,
                serverFields: $serverFields,
                enumClasses: $enumClasses,
                superRefines: $superRefines,
                dependencies: $dependencies,
                confirmationFields: $elementConfirmations,
            );

            $ctx->setBase('z.array('.$elementExpr.')');
        } elseif ($node->isObject()) {
            $objectExpr = $this->emitObjectBody(
                node: $node,
                indent: $indent,
                customMessages: $customMessages,
                customAttributes: $customAttributes,
                locale: $locale,
                messages: $messages,
                serverFields: $serverFields,
                enumClasses: $enumClasses,
                superRefines: $superRefines,
                dependencies: $dependencies,
            );

            $ctx->setBase($objectExpr);
        }

        foreach ($node->rules as $rule) {
            $category = $this->classifier->classify($rule);

            if ($category === RuleCategory::Server) {
                $serverFields[] = $node->path;

                continue;
            }

            if ($category === RuleCategory::Meta) {
                continue;
            }

            $ctx->setCurrentRule($rule);
            $this->applyRule($rule, $ctx);
        }

        $messages = [...$messages, ...$ctx->messages()];
        $enumClasses = [...$enumClasses, ...$ctx->enumClasses()];
        $superRefines = [...$superRefines, ...$ctx->superRefines()];
        $confirmationFields = [...$confirmationFields, ...$ctx->confirmationFields()];

        foreach ($ctx->dependencies() as $trigger => $dependents) {
            $dependencies[$trigger] = [...($dependencies[$trigger] ?? []), ...$dependents];
        }

        // For implicit container nodes (no explicit rules), emit the container directly without wrapping.
        if ($node->implicit && $node->rules === []) {
            if ($node->isObject() || $node->isArray()) {
                return $ctx->build();
            }
        }

        return $ctx->build();
    }

    private function applyRule(NormalizedRule $rule, ZodContext $ctx): void
    {
        if ($rule->source instanceof CastsToZod) {
            $rule->source->toZod($ctx);

            return;
        }

        if ($this->registry !== null && $this->registry->hasMapper($rule->name)) {
            $registered = $this->registry->mapper($rule->name);

            if ($registered instanceof Closure) {
                $registered($ctx, $rule);

                return;
            }

            if (is_string($registered) && is_subclass_of($registered, RuleMapper::class)) {
                (new $registered)->map($rule, $ctx);

                return;
            }
        }

        $mapper = $this->mappers[$rule->name] ?? null;

        if ($mapper !== null) {
            $mapper->map($rule, $ctx);
        }
    }

    private function inferNodeType(FieldNode $node): FieldType
    {
        if ($node->isArray()) {
            return FieldType::Array;
        }

        return $this->typeInferrer->infer($node->rules);
    }

    private function registerDefaultMappers(): void
    {
        $groups = [
            new PresenceMappers,
            new TypeMappers,
            new SizeMappers,
            new FormatMappers,
            new SetMappers,
            new DateMappers,
            new FileMappers,
            new PasswordMapper,
            new CrossFieldMappers,
        ];

        foreach ($groups as $mapper) {
            foreach ($mapper::rules() as $ruleName) {
                $this->mappers[$ruleName] = $mapper;
            }
        }
    }

    private function localConfirmationKey(string $parentPath, string $confirmationPath): ?string
    {
        if ($parentPath === '') {
            return str_contains($confirmationPath, '.') ? null : $confirmationPath;
        }

        $prefix = $parentPath.'.';

        if (! str_starts_with($confirmationPath, $prefix)) {
            return null;
        }

        $remainder = substr($confirmationPath, strlen($prefix));

        return str_contains($remainder, '.') ? null : $remainder;
    }

    private function formatPropertyKey(string $key): string
    {
        if (preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $key) === 1) {
            return $key;
        }

        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $key)."'";
    }
}
