<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Messages;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Str;
use Rylacdang\Formcast\Rules\FieldType;
use Rylacdang\Formcast\Rules\NormalizedRule;

final class MessageResolver
{
    /** @var list<string> */
    private const SIZE_VARIANT_RULES = [
        'min',
        'max',
        'size',
        'between',
        'gt',
        'gte',
        'lt',
        'lte',
    ];

    public function __construct(
        private readonly Translator $translator,
    ) {}

    /**
     * Resolve a Laravel-identical validation error message for a field and rule.
     *
     * @param  array<string, string>  $customMessages
     * @param  array<string, string>  $customAttributes
     */
    public function resolve(
        string $field,
        NormalizedRule $rule,
        FieldType $fieldType = FieldType::String,
        array $customMessages = [],
        array $customAttributes = [],
        ?string $locale = null,
    ): string {
        $template = $this->findTemplate($field, $rule->name, $fieldType, $customMessages, $locale);

        return $this->replacePlaceholders($template, $field, $rule, $customAttributes, $locale);
    }

    /**
     * Resolve the human-readable attribute name for a field.
     *
     * @param  array<string, string>  $customAttributes
     */
    public function resolveAttribute(
        string $field,
        array $customAttributes = [],
        ?string $locale = null,
    ): string {
        if (isset($customAttributes[$field])) {
            return $customAttributes[$field];
        }

        foreach ($customAttributes as $pattern => $label) {
            if (str_contains($pattern, '*') && Str::is($pattern, $field)) {
                return $label;
            }
        }

        $langKey = 'validation.attributes.'.$field;
        $translated = $this->translator->get($langKey, [], $locale);

        if (is_string($translated) && $translated !== $langKey) {
            return $translated;
        }

        return str_replace('_', ' ', Str::snake($field));
    }

    /**
     * @param  array<string, string>  $customMessages
     */
    private function findTemplate(
        string $field,
        string $ruleName,
        FieldType $fieldType,
        array $customMessages,
        ?string $locale,
    ): string {
        // 1. FormRequest::messages() — exact field.rule, wildcard match, then rule-only.
        $exactKey = $field.'.'.$ruleName;

        if (isset($customMessages[$exactKey])) {
            return $customMessages[$exactKey];
        }

        foreach ($customMessages as $key => $message) {
            if (str_contains($key, '*') && Str::is($key, $exactKey)) {
                return $message;
            }
        }

        if (isset($customMessages[$ruleName])) {
            return $customMessages[$ruleName];
        }

        // 2. lang/{locale}/validation.php `custom` array.
        $langCustomKey = 'validation.custom.'.$field.'.'.$ruleName;
        $langCustom = $this->translator->get($langCustomKey, [], $locale);

        if (is_string($langCustom) && $langCustom !== $langCustomKey) {
            return $langCustom;
        }

        // 3. Default lang/{locale}/validation.php lines (with size variants).
        if (in_array($ruleName, self::SIZE_VARIANT_RULES, true)) {
            $variantKey = 'validation.'.$ruleName.'.'.$fieldType->sizeVariant();
            $variantLine = $this->translator->get($variantKey, [], $locale);

            if (is_string($variantLine) && $variantLine !== $variantKey) {
                return $variantLine;
            }
        }

        $defaultKey = 'validation.'.$ruleName;
        $defaultLine = $this->translator->get($defaultKey, [], $locale);

        if (is_string($defaultLine) && $defaultLine !== $defaultKey) {
            return $defaultLine;
        }

        if (is_array($defaultLine)) {
            $variant = $defaultLine[$fieldType->sizeVariant()] ?? reset($defaultLine);

            if (is_string($variant)) {
                return $variant;
            }
        }

        return 'The :attribute field is invalid.';
    }

    /**
     * @param  array<string, string>  $customAttributes
     */
    private function replacePlaceholders(
        string $template,
        string $field,
        NormalizedRule $rule,
        array $customAttributes,
        ?string $locale,
    ): string {
        $attribute = $this->resolveAttribute($field, $customAttributes, $locale);

        $replacements = [
            ':attribute' => $attribute,
            ':ATTRIBUTE' => Str::upper($attribute),
            ':Attribute' => Str::ucfirst($attribute),
            ...$this->ruleReplacements($rule, $customAttributes, $locale),
        ];

        return strtr($template, $replacements);
    }

    /**
     * @param  array<string, string>  $customAttributes
     * @return array<string, string>
     */
    private function ruleReplacements(
        NormalizedRule $rule,
        array $customAttributes,
        ?string $locale,
    ): array {
        $params = $rule->parameters;

        return match ($rule->name) {
            'min', 'min_digits' => [':min' => $params[0] ?? ''],
            'max', 'max_digits' => [':max' => $params[0] ?? ''],
            'size' => [':size' => $params[0] ?? ''],
            'between', 'digits_between' => [
                ':min' => $params[0] ?? '',
                ':max' => $params[1] ?? '',
            ],
            'digits' => [':digits' => $params[0] ?? ''],
            'multiple_of' => [':value' => $params[0] ?? ''],
            'decimal' => [
                ':decimal' => isset($params[1]) ? $params[0].'-'.$params[1] : ($params[0] ?? ''),
            ],
            'same', 'different', 'in_array', 'required_if_accepted', 'required_if_declined' => [
                ':other' => $this->resolveAttribute($params[0] ?? '', $customAttributes, $locale),
            ],
            'gt', 'gte', 'lt', 'lte' => [
                ':value' => $this->resolveAttribute($params[0] ?? '', $customAttributes, $locale),
            ],
            'starts_with', 'ends_with', 'doesnt_start_with', 'doesnt_end_with', 'mimes', 'mimetypes', 'extensions' => [
                ':values' => implode(', ', $params),
            ],
            'required_if', 'required_unless', 'prohibited_if', 'prohibited_unless', 'missing_if', 'accepted_if', 'declined_if' => [
                ':other' => $this->resolveAttribute($params[0] ?? '', $customAttributes, $locale),
                ':value' => implode(', ', array_slice($params, 1)),
                ':values' => implode(', ', array_slice($params, 1)),
            ],
            'required_with', 'required_with_all', 'required_without', 'required_without_all' => [
                ':values' => implode(
                    ' / ',
                    array_map(
                        fn (string $other): string => $this->resolveAttribute($other, $customAttributes, $locale),
                        $params,
                    ),
                ),
            ],
            'date_format' => [':format' => implode(', ', $params)],
            'date_equals', 'after', 'after_or_equal', 'before', 'before_or_equal' => [
                ':date' => $this->resolveDateParameter($params[0] ?? '', $customAttributes, $locale),
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, string>  $customAttributes
     */
    private function resolveDateParameter(
        string $parameter,
        array $customAttributes,
        ?string $locale,
    ): string {
        if (isset($customAttributes[$parameter])) {
            return $customAttributes[$parameter];
        }

        $langKey = 'validation.attributes.'.$parameter;
        $translated = $this->translator->get($langKey, [], $locale);

        if (is_string($translated) && $translated !== $langKey) {
            return $translated;
        }

        return $parameter;
    }
}
