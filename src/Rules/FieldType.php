<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Rules;

enum FieldType: string
{
    case String = 'string';
    case Numeric = 'numeric';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Array = 'array';
    case File = 'file';
    case Date = 'date';
    case Mixed = 'mixed';

    /**
     * Return Laravel's validation message size variant (`numeric`, `array`, `file`, or `string`).
     */
    public function sizeVariant(): string
    {
        return match ($this) {
            self::Integer, self::Numeric => 'numeric',
            self::Array => 'array',
            self::File => 'file',
            default => 'string',
        };
    }
}
