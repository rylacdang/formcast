<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Emitters\Mappers;

use Rylacdang\Formcast\Contracts\RuleMapper;
use Rylacdang\Formcast\Emitters\ZodContext;
use Rylacdang\Formcast\Rules\FieldType;
use Rylacdang\Formcast\Rules\NormalizedRule;

final class SizeMappers implements RuleMapper
{
    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'min',
            'max',
            'size',
            'between',
            'digits',
            'digits_between',
            'min_digits',
            'max_digits',
            'multiple_of',
        ];
    }

    public function map(NormalizedRule $rule, ZodContext $ctx): void
    {
        $p0 = $rule->parameters[0] ?? '0';
        $p1 = $rule->parameters[1] ?? '0';

        match ($rule->name) {
            'min' => $this->mapMin($p0, $ctx),
            'max' => $this->mapMax($p0, $ctx),
            'size' => $this->mapSize($p0, $ctx),
            'between' => $this->mapBetween($p0, $p1, $ctx),
            'digits' => $ctx->chain('refine', 'fc.isDigits('.$p0.')', $ctx->message()),
            'digits_between' => $ctx->chain('refine', 'fc.isDigitsBetween('.$p0.', '.$p1.')', $ctx->message()),
            'min_digits' => $ctx->chain('refine', 'fc.isMinDigits('.$p0.')', $ctx->message()),
            'max_digits' => $ctx->chain('refine', 'fc.isMaxDigits('.$p0.')', $ctx->message()),
            'multiple_of' => $ctx->chain('multipleOf', $p0, $ctx->message()),
            default => null,
        };
    }

    private function mapMin(string $value, ZodContext $ctx): void
    {
        if ($ctx->fieldType === FieldType::File) {
            $ctx->chain('refine', 'fc.fileMinKb('.$value.')', $ctx->message());

            return;
        }

        $ctx->chain('min', $value, $ctx->message());
    }

    private function mapMax(string $value, ZodContext $ctx): void
    {
        if ($ctx->fieldType === FieldType::File) {
            $ctx->chain('refine', 'fc.fileMaxKb('.$value.')', $ctx->message());

            return;
        }

        $ctx->chain('max', $value, $ctx->message());
    }

    private function mapSize(string $value, ZodContext $ctx): void
    {
        match ($ctx->fieldType) {
            FieldType::Integer, FieldType::Numeric => $ctx->chain('refine', '(v) => Number(v) === '.$value, $ctx->message()),
            FieldType::File => $ctx->chain('refine', 'fc.fileSizeKb('.$value.')', $ctx->message()),
            default => $ctx->chain('length', $value, $ctx->message()),
        };
    }

    private function mapBetween(string $min, string $max, ZodContext $ctx): void
    {
        $msg = $ctx->message();

        if ($ctx->fieldType === FieldType::File) {
            $ctx->chain('refine', 'fc.fileBetweenKb('.$min.', '.$max.')', $msg);

            return;
        }

        $ctx->chain('min', $min, $msg);
        $ctx->chain('max', $max, $msg);
    }
}
