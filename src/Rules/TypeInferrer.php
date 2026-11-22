<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Rules;

final class TypeInferrer
{
    /** @var list<string> */
    private const INTEGER_RULES = [
        'integer',
        'digits',
        'digits_between',
        'min_digits',
        'max_digits',
    ];

    /** @var list<string> */
    private const NUMERIC_RULES = [
        'numeric',
        'decimal',
        'multiple_of',
    ];

    /** @var list<string> */
    private const FILE_RULES = [
        'file',
        'image',
        'mimes',
        'mimetypes',
        'extensions',
    ];

    /** @var list<string> */
    private const ARRAY_RULES = [
        'array',
        'list',
    ];

    /** @var list<string> */
    private const BOOLEAN_RULES = [
        'boolean',
        'accepted',
        'accepted_if',
        'declined',
        'declined_if',
    ];

    /** @var list<string> */
    private const DATE_RULES = [
        'date',
        'date_format',
        'date_equals',
        'after',
        'after_or_equal',
        'before',
        'before_or_equal',
    ];

    /** @var list<string> */
    private const STRING_RULES = [
        'string',
        'email',
        'url',
        'uuid',
        'ulid',
        'ip',
        'ipv4',
        'ipv6',
        'mac_address',
        'hex_color',
        'alpha',
        'alpha_num',
        'alpha_dash',
        'ascii',
        'lowercase',
        'uppercase',
        'starts_with',
        'ends_with',
        'doesnt_start_with',
        'doesnt_end_with',
        'regex',
        'not_regex',
        'json',
        'password',
    ];

    /**
     * Infer the field type from normalized rules using Laravel's getSize() precedence.
     *
     * @param  list<NormalizedRule>  $rules
     */
    public function infer(array $rules): FieldType
    {
        $names = array_map(static fn (NormalizedRule $rule): string => $rule->name, $rules);

        // Numeric rules take precedence in Laravel's getSize() check.
        $hasNumeric = $this->hasAny($names, self::NUMERIC_RULES);
        $hasInteger = $this->hasAny($names, self::INTEGER_RULES);

        if ($hasInteger && ! $hasNumeric) {
            return FieldType::Integer;
        }

        if ($hasNumeric) {
            return FieldType::Numeric;
        }

        if ($this->hasAny($names, self::FILE_RULES)) {
            return FieldType::File;
        }

        if ($this->hasAny($names, self::ARRAY_RULES)) {
            return FieldType::Array;
        }

        if ($this->hasAny($names, self::BOOLEAN_RULES)) {
            return FieldType::Boolean;
        }

        if ($this->hasAny($names, self::DATE_RULES)) {
            return FieldType::Date;
        }

        if ($this->hasAny($names, self::STRING_RULES)) {
            return FieldType::String;
        }

        return FieldType::Mixed;
    }

    /**
     * @param  list<string>  $names
     * @param  list<string>  $candidates
     */
    private function hasAny(array $names, array $candidates): bool
    {
        return array_intersect($names, $candidates) !== [];
    }
}
