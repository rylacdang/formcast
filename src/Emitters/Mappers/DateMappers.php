<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Emitters\Mappers;

use Rylacdang\Formcast\Contracts\RuleMapper;
use Rylacdang\Formcast\Emitters\ZodContext;
use Rylacdang\Formcast\Rules\NormalizedRule;

final class DateMappers implements RuleMapper
{
    /** @var list<string> */
    private const RELATIVE_DATES = [
        'today',
        'tomorrow',
        'yesterday',
        'now',
    ];

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'date',
            'date_format',
            'date_equals',
            'after',
            'after_or_equal',
            'before',
            'before_or_equal',
        ];
    }

    public function map(NormalizedRule $rule, ZodContext $ctx): void
    {
        $target = $rule->parameters[0] ?? '';

        match ($rule->name) {
            'date' => $ctx->setBase('fc.date('.$ctx->message().')'),
            'date_format' => $this->mapDateFormat($rule->parameters, $ctx),
            'date_equals' => $this->mapComparison('equals', $target, $ctx),
            'after' => $this->mapComparison('after', $target, $ctx),
            'after_or_equal' => $this->mapComparison('afterOrEqual', $target, $ctx),
            'before' => $this->mapComparison('before', $target, $ctx),
            'before_or_equal' => $this->mapComparison('beforeOrEqual', $target, $ctx),
            default => null,
        };
    }

    /**
     * @param  list<string>  $formats
     */
    private function mapDateFormat(array $formats, ZodContext $ctx): void
    {
        $quoted = implode(', ', array_map($ctx->quote(...), $formats));
        $ctx->chain('refine', 'fc.dateFormat(['.$quoted.'])', $ctx->message());
    }

    private function mapComparison(string $op, string $target, ZodContext $ctx): void
    {
        $lower = strtolower($target);

        if (in_array($lower, self::RELATIVE_DATES, true)) {
            $ctx->chain('refine', 'fc.dateCompare('.$ctx->quote($op).', fc.date.'.$lower.')', $ctx->message());

            return;
        }

        if ($this->looksLikeDateLiteral($target)) {
            $ctx->chain('refine', 'fc.dateCompare('.$ctx->quote($op).', '.$ctx->quote($target).')', $ctx->message());

            return;
        }

        if ($target !== '') {
            $ctx->addSuperRefine(sprintf(
                'fc.dateCompareField(%s, %s, %s, %s)',
                $ctx->quote($ctx->field),
                $ctx->quote($op),
                $ctx->quote($target),
                $ctx->message(),
            ));
            $ctx->addDependency($target, $ctx->field);
        }
    }

    private function looksLikeDateLiteral(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1 || strtotime($value) !== false;
    }
}
