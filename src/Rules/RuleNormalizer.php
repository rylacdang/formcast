<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Rules;

use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\ConditionalRules;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\ExcludeIf;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\NotIn;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\ProhibitedIf;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Rules\Unique;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Stringable;
use UnitEnum;

final class RuleNormalizer
{
    /**
     * Normalize a full ruleset map (`field => rules`).
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, list<NormalizedRule>>
     */
    public function normalizeAll(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $field => $fieldRules) {
            $normalized[$field] = $this->normalizeField($fieldRules);
        }

        return $normalized;
    }

    /**
     * Normalize the rule definition for a single field.
     *
     * @return list<NormalizedRule>
     */
    public function normalizeField(mixed $rules): array
    {
        if (is_string($rules)) {
            return $this->parseStringRules($rules);
        }

        if (is_array($rules)) {
            $result = [];

            foreach ($rules as $rule) {
                $result = [...$result, ...$this->normalizeField($rule)];
            }

            return $result;
        }

        return $this->normalizeObjectRule($rules);
    }

    /**
     * Split a pipe-delimited rule string while keeping regex patterns intact.
     *
     * @return list<NormalizedRule>
     */
    private function parseStringRules(string $rules): array
    {
        $trimmed = trim($rules);

        if ($trimmed === '') {
            return [];
        }

        if ($this->isRegexRuleString($trimmed)) {
            $parsed = $this->parseSingleStringRule($trimmed);

            return $parsed !== null ? [$parsed] : [];
        }

        $segments = $this->splitPipeRules($trimmed);
        $result = [];

        foreach ($segments as $segment) {
            $parsed = $this->parseSingleStringRule($segment);

            if ($parsed !== null) {
                $result[] = $parsed;
            }
        }

        return $result;
    }

    /**
     * Split a string on `|` without splitting pipes inside regex:/.../ or not_regex:/.../.
     *
     * @return list<string>
     */
    private function splitPipeRules(string $rules): array
    {
        if (! str_contains($rules, 'regex:') && ! str_contains($rules, 'not_regex:')) {
            return explode('|', $rules);
        }

        $segments = [];
        $length = strlen($rules);
        $current = '';
        $i = 0;

        while ($i < $length) {
            $remaining = substr($rules, $i);

            if ($current === '' && ($this->isRegexRuleString($remaining))) {
                $colonPos = strpos($remaining, ':');
                $prefix = substr($remaining, 0, (int) $colonPos + 1);
                $patternStart = $i + strlen($prefix);

                if ($patternStart < $length) {
                    $delimiter = $rules[$patternStart];
                    $closingDelimiter = $this->closingDelimiter($delimiter);
                    $cursor = $patternStart + 1;
                    $escaped = false;

                    while ($cursor < $length) {
                        $char = $rules[$cursor];

                        if ($escaped) {
                            $escaped = false;
                            $cursor++;

                            continue;
                        }

                        if ($char === '\\') {
                            $escaped = true;
                            $cursor++;

                            continue;
                        }

                        if ($char === $closingDelimiter) {
                            $cursor++;

                            // Consume trailing PCRE flags until `|` or end of string.
                            while ($cursor < $length && $rules[$cursor] !== '|') {
                                $cursor++;
                            }

                            break;
                        }

                        $cursor++;
                    }

                    $segments[] = substr($rules, $i, $cursor - $i);
                    $i = $cursor + 1; // Skip the `|` if present.

                    continue;
                }
            }

            if ($rules[$i] === '|') {
                $segments[] = $current;
                $current = '';
            } else {
                $current .= $rules[$i];
            }

            $i++;
        }

        if ($current !== '') {
            $segments[] = $current;
        }

        return $segments;
    }

    private function parseSingleStringRule(string $rule): ?NormalizedRule
    {
        $trimmed = trim($rule);

        if ($trimmed === '') {
            return null;
        }

        $parts = explode(':', $trimmed, 2);
        $name = Str::snake(trim($parts[0]));

        if ($name === '') {
            return null;
        }

        if (! isset($parts[1])) {
            return new NormalizedRule(name: $name);
        }

        $parameterString = $parts[1];

        if ($name === 'regex' || $name === 'not_regex') {
            return new NormalizedRule(name: $name, parameters: [$parameterString]);
        }

        $parsed = str_getcsv($parameterString, ',', '"', '');
        $parameters = [];

        foreach ($parsed as $param) {
            if ($param !== null) {
                $parameters[] = $param;
            }
        }

        return new NormalizedRule(name: $name, parameters: $parameters);
    }

    /**
     * @return list<NormalizedRule>
     */
    private function normalizeObjectRule(mixed $rule): array
    {
        if ($rule instanceof Closure) {
            return [new NormalizedRule(name: 'closure', source: $rule, forceServer: true)];
        }

        if ($rule instanceof In || $rule instanceof NotIn) {
            return $this->parseStringRules((string) $rule);
        }

        if ($rule instanceof Unique) {
            return [new NormalizedRule(name: 'unique', source: $rule, forceServer: true)];
        }

        if ($rule instanceof Exists) {
            return [new NormalizedRule(name: 'exists', source: $rule, forceServer: true)];
        }

        if ($rule instanceof Dimensions) {
            return [new NormalizedRule(name: 'dimensions', source: $rule, forceServer: true)];
        }

        if ($rule instanceof RequiredIf) {
            return $this->normalizeConditionalBooleanRule($rule, 'required', 'required_if');
        }

        if ($rule instanceof ProhibitedIf) {
            return $this->normalizeConditionalBooleanRule($rule, 'prohibited', 'prohibited_if');
        }

        if ($rule instanceof ExcludeIf) {
            return $this->normalizeConditionalBooleanRule($rule, 'exclude', 'exclude_if');
        }

        if ($rule instanceof Enum) {
            return $this->normalizeEnumRule($rule);
        }

        if ($rule instanceof Password) {
            return $this->normalizePasswordRule($rule);
        }

        if ($rule instanceof File) {
            return $this->normalizeFileRule($rule);
        }

        if ($rule instanceof ConditionalRules) {
            return $this->normalizeConditionalRules($rule);
        }

        if (is_object($rule)) {
            if ($rule instanceof Stringable && ! $this->isCustomValidationObject($rule)) {
                return $this->parseStringRules((string) $rule);
            }

            return [new NormalizedRule(name: $rule::class, source: $rule, forceServer: true)];
        }

        return [];
    }

    /**
     * @return list<NormalizedRule>
     */
    private function normalizeConditionalBooleanRule(
        RequiredIf|ProhibitedIf|ExcludeIf $rule,
        string $staticRuleName,
        string $serverRuleName,
    ): array {
        if (is_bool($rule->condition)) {
            return $rule->condition ? [new NormalizedRule(name: $staticRuleName)] : [];
        }

        return [new NormalizedRule(name: $serverRuleName, source: $rule, forceServer: true)];
    }

    /**
     * @return list<NormalizedRule>
     */
    private function normalizeEnumRule(Enum $rule): array
    {
        /** @var class-string<UnitEnum> $type */
        $type = $this->readProperty($rule, 'type');

        /** @var list<UnitEnum> $only */
        $only = $this->readProperty($rule, 'only') ?? [];

        /** @var list<UnitEnum> $except */
        $except = $this->readProperty($rule, 'except') ?? [];

        $parameters = [$type];

        if ($only !== []) {
            $parameters[] = 'only='.implode(',', array_map($this->enumCaseValue(...), $only));
        }

        if ($except !== []) {
            $parameters[] = 'except='.implode(',', array_map($this->enumCaseValue(...), $except));
        }

        return [new NormalizedRule(name: 'enum', parameters: $parameters, source: $rule)];
    }

    /**
     * @return list<NormalizedRule>
     */
    private function normalizePasswordRule(Password $rule): array
    {
        $rules = [];

        if ($this->readProperty($rule, 'required') === true) {
            $rules[] = new NormalizedRule(name: 'required');
        }

        if ($this->readProperty($rule, 'sometimes') === true) {
            $rules[] = new NormalizedRule(name: 'sometimes');
        }

        $params = [];
        $min = $this->readProperty($rule, 'min');

        if (is_int($min)) {
            $params[] = 'min='.$min;
        }

        $max = $this->readProperty($rule, 'max');

        if (is_int($max)) {
            $params[] = 'max='.$max;
        }

        foreach (['letters', 'mixedCase', 'numbers', 'symbols'] as $flag) {
            if ($this->readProperty($rule, $flag) === true) {
                $params[] = Str::snake($flag);
            }
        }

        $rules[] = new NormalizedRule(name: 'password', parameters: $params, source: $rule);

        if ($this->readProperty($rule, 'uncompromised') === true) {
            $rules[] = new NormalizedRule(name: 'password_uncompromised', source: $rule, forceServer: true);
        }

        $customRules = $this->readProperty($rule, 'customRules');

        if (is_array($customRules) && $customRules !== []) {
            $rules = [...$rules, ...$this->normalizeField($customRules)];
        }

        return $rules;
    }

    /**
     * @return list<NormalizedRule>
     */
    private function normalizeFileRule(File $rule): array
    {
        $method = new ReflectionMethod($rule, 'buildValidationRules');
        $builtRules = $method->invoke($rule);

        if (! is_array($builtRules)) {
            return [new NormalizedRule(name: 'file', source: $rule)];
        }

        return $this->normalizeField($builtRules);
    }

    /**
     * @return list<NormalizedRule>
     */
    private function normalizeConditionalRules(ConditionalRules $rule): array
    {
        $condition = $this->readProperty($rule, 'condition');

        if (! is_bool($condition)) {
            return [new NormalizedRule(name: 'conditional', source: $rule, forceServer: true)];
        }

        $branch = $condition
            ? $this->readProperty($rule, 'rules')
            : $this->readProperty($rule, 'defaultRules');

        return $this->normalizeField($branch);
    }

    private function isRegexRuleString(string $value): bool
    {
        $lower = strtolower($value);

        return str_starts_with($lower, 'regex:') || str_starts_with($lower, 'not_regex:');
    }

    private function closingDelimiter(string $delimiter): string
    {
        return match ($delimiter) {
            '(' => ')',
            '[' => ']',
            '{' => '}',
            '<' => '>',
            default => $delimiter,
        };
    }

    private function enumCaseValue(UnitEnum $case): string
    {
        return $case instanceof \BackedEnum ? (string) $case->value : $case->name;
    }

    private function isCustomValidationObject(object $rule): bool
    {
        $reflection = new ReflectionClass($rule);

        return $reflection->implementsInterface(ValidationRule::class)
            || $reflection->implementsInterface(Rule::class);
    }

    private function readProperty(object $object, string $property): mixed
    {
        $reflection = new ReflectionProperty($object, $property);

        return $reflection->getValue($object);
    }
}
