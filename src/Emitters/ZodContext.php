<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Emitters;

use Rylacdang\Formcast\Messages\MessageResolver;
use Rylacdang\Formcast\Rules\FieldType;
use Rylacdang\Formcast\Rules\NormalizedRule;
use UnitEnum;

final class ZodContext
{
    private string $base = 'fc.string()';

    private bool $hasExplicitBase = false;

    /** @var list<string> */
    private array $chains = [];

    private bool $required = false;

    private ?string $requiredMessageRef = null;

    private bool $filled = false;

    private ?string $filledMessageRef = null;

    private bool $present = false;

    private ?string $presentMessageRef = null;

    private bool $nullable = false;

    private bool $sometimes = false;

    private bool $prohibited = false;

    private ?string $prohibitedMessageRef = null;

    private bool $missing = false;

    private ?string $missingMessageRef = null;

    /** @var array<string, string> */
    private array $messages = [];

    /** @var list<class-string<UnitEnum>> */
    private array $enumClasses = [];

    /** @var list<string> */
    private array $superRefines = [];

    /** @var array<string, list<string>> */
    private array $dependencies = [];

    private ?NormalizedRule $currentRule = null;

    /**
     * @param  array<string, string>  $customMessages
     * @param  array<string, string>  $customAttributes
     */
    public function __construct(
        public readonly string $field,
        public readonly FieldType $fieldType,
        private readonly MessageResolver $messageResolver,
        private readonly array $customMessages = [],
        private readonly array $customAttributes = [],
        private readonly ?string $locale = null,
    ) {
        $this->base = match ($fieldType) {
            FieldType::Integer => 'fc.integer()',
            FieldType::Numeric => 'fc.numeric()',
            FieldType::Boolean => 'fc.boolean()',
            FieldType::File => 'fc.file()',
            FieldType::Date => 'fc.date()',
            FieldType::Array => 'z.array(z.unknown())',
            FieldType::String => 'fc.string()',
            FieldType::Mixed => 'z.unknown()',
        };
    }

    public function setCurrentRule(NormalizedRule $rule): void
    {
        $this->currentRule = $rule;
    }

    public function currentRule(): ?NormalizedRule
    {
        return $this->currentRule;
    }

    public function setBase(string $expression): self
    {
        $this->base = $expression;
        $this->hasExplicitBase = true;

        return $this;
    }

    public function hasExplicitBase(): bool
    {
        return $this->hasExplicitBase;
    }

    /**
     * Append a method call to the schema chain and return the formatted call.
     */
    public function chain(string $method, string ...$args): string
    {
        $call = '.'.$method.'('.implode(', ', $args).')';
        $this->chains[] = $call;

        return $call;
    }

    public function markRequired(string $messageRef): self
    {
        $this->required = true;
        $this->requiredMessageRef = $messageRef;

        return $this;
    }

    public function markFilled(string $messageRef): self
    {
        $this->filled = true;
        $this->filledMessageRef = $messageRef;

        return $this;
    }

    public function markPresent(string $messageRef): self
    {
        $this->present = true;
        $this->presentMessageRef = $messageRef;

        return $this;
    }

    public function markNullable(): self
    {
        $this->nullable = true;

        return $this;
    }

    public function markSometimes(): self
    {
        $this->sometimes = true;

        return $this;
    }

    public function markProhibited(string $messageRef): self
    {
        $this->prohibited = true;
        $this->prohibitedMessageRef = $messageRef;

        return $this;
    }

    public function markMissing(string $messageRef): self
    {
        $this->missing = true;
        $this->missingMessageRef = $messageRef;

        return $this;
    }

    /**
     * Resolve a message for the current rule (or a custom rule key) and return its `m['...']` reference.
     */
    public function message(?string $ruleKey = null, ?NormalizedRule $rule = null): string
    {
        $targetRule = $rule ?? $this->currentRule;

        if ($ruleKey !== null && str_starts_with($ruleKey, 'validation.')) {
            $suffix = substr($ruleKey, strlen('validation.'));
            $syntheticRule = new NormalizedRule($suffix, $targetRule !== null ? $targetRule->parameters : []);
            $key = $this->field.'.'.$suffix;
            $this->messages[$key] = $this->messageResolver->resolve(
                field: $this->field,
                rule: $syntheticRule,
                fieldType: $this->fieldType,
                customMessages: $this->customMessages,
                customAttributes: $this->customAttributes,
                locale: $this->locale,
            );

            return "m['".$this->escapeSingleQuotes($key)."']";
        }

        $resolvedRule = $targetRule ?? new NormalizedRule($ruleKey ?? 'invalid');
        $keySuffix = $ruleKey ?? $resolvedRule->name;
        $key = $this->field.'.'.$keySuffix;

        $this->messages[$key] = $this->messageResolver->resolve(
            field: $this->field,
            rule: $resolvedRule,
            fieldType: $this->fieldType,
            customMessages: $this->customMessages,
            customAttributes: $this->customAttributes,
            locale: $this->locale,
        );

        return "m['".$this->escapeSingleQuotes($key)."']";
    }

    /**
     * @param  class-string<UnitEnum>  $enumClass
     */
    public function addEnumImport(string $enumClass): self
    {
        if (! in_array($enumClass, $this->enumClasses, true)) {
            $this->enumClasses[] = $enumClass;
        }

        return $this;
    }

    public function addSuperRefine(string $expression): self
    {
        $this->superRefines[] = $expression;

        return $this;
    }

    public function addDependency(string $triggerField, string $dependentField): self
    {
        $this->dependencies[$triggerField] ??= [];

        if (! in_array($dependentField, $this->dependencies[$triggerField], true)) {
            $this->dependencies[$triggerField][] = $dependentField;
        }

        return $this;
    }

    public function quote(string $value): string
    {
        return "'".$this->escapeSingleQuotes($value)."'";
    }

    public function build(): string
    {
        $expression = $this->base.implode('', $this->chains);

        if ($this->prohibited) {
            return 'fc.prohibited('.$this->prohibitedMessageRef.')';
        }

        if ($this->missing) {
            return 'fc.missing('.$this->missingMessageRef.')';
        }

        if ($this->required) {
            $wrapped = 'fc.required('.$expression.', '.$this->requiredMessageRef.')';

            return $this->sometimes ? 'fc.sometimes('.$wrapped.')' : $wrapped;
        }

        if ($this->filled) {
            return 'fc.filled('.$expression.', '.$this->filledMessageRef.')';
        }

        if ($this->present) {
            $wrapped = 'fc.present('.$expression.', '.$this->presentMessageRef.')';

            return $this->nullable ? 'fc.nullable('.$wrapped.')' : $wrapped;
        }

        if ($this->nullable) {
            return 'fc.nullable('.$expression.')';
        }

        if ($this->sometimes) {
            return 'fc.sometimes('.$expression.')';
        }

        return 'fc.optional('.$expression.')';
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * @return list<class-string<UnitEnum>>
     */
    public function enumClasses(): array
    {
        return $this->enumClasses;
    }

    /**
     * @return list<string>
     */
    public function superRefines(): array
    {
        return $this->superRefines;
    }

    /**
     * @return array<string, list<string>>
     */
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    private function escapeSingleQuotes(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
