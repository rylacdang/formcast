<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Emitters\Mappers;

use Rylacdang\Formcast\Contracts\RuleMapper;
use Rylacdang\Formcast\Emitters\ZodContext;
use Rylacdang\Formcast\Rules\NormalizedRule;

final class TypeMappers implements RuleMapper
{
    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'string',
            'integer',
            'numeric',
            'boolean',
            'array',
            'list',
            'decimal',
            'json',
        ];
    }

    public function map(NormalizedRule $rule, ZodContext $ctx): void
    {
        match ($rule->name) {
            'string' => $ctx->setBase('fc.string('.$ctx->message().')'),
            'integer' => $ctx->setBase('fc.integer('.$ctx->message().')'),
            'numeric' => $ctx->setBase('fc.numeric('.$ctx->message().')'),
            'boolean' => $ctx->setBase('fc.boolean('.$ctx->message().')'),
            'array' => $this->mapArray($ctx),
            'list' => $this->mapList($ctx),
            'decimal' => $this->mapDecimal($rule, $ctx),
            'json' => $ctx->setBase('fc.json('.$ctx->message().')'),
            default => null,
        };
    }

    private function mapArray(ZodContext $ctx): void
    {
        if (! $ctx->hasExplicitBase()) {
            $ctx->setBase('z.array(z.unknown(), { message: '.$ctx->message().' })');
        }
    }

    private function mapList(ZodContext $ctx): void
    {
        if (! $ctx->hasExplicitBase()) {
            $ctx->setBase('z.array(z.unknown(), { message: '.$ctx->message().' })');
        }
    }

    private function mapDecimal(NormalizedRule $rule, ZodContext $ctx): void
    {
        $min = $rule->parameters[0] ?? '0';
        $max = $rule->parameters[1] ?? null;

        $args = $max !== null
            ? $min.', '.$max.', '.$ctx->message()
            : $min.', undefined, '.$ctx->message();

        $ctx->setBase('fc.decimal('.$args.')');
    }
}
