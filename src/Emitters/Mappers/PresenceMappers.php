<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Emitters\Mappers;

use Rylacdang\Formcast\Contracts\RuleMapper;
use Rylacdang\Formcast\Emitters\ZodContext;
use Rylacdang\Formcast\Rules\NormalizedRule;

final class PresenceMappers implements RuleMapper
{
    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'required',
            'filled',
            'present',
            'nullable',
            'sometimes',
            'missing',
            'prohibited',
            'accepted',
            'declined',
        ];
    }

    public function map(NormalizedRule $rule, ZodContext $ctx): void
    {
        match ($rule->name) {
            'required' => $ctx->markRequired($ctx->message()),
            'filled' => $ctx->markFilled($ctx->message()),
            'present' => $ctx->markPresent($ctx->message()),
            'nullable' => $ctx->markNullable(),
            'sometimes' => $ctx->markSometimes(),
            'missing' => $ctx->markMissing($ctx->message()),
            'prohibited' => $ctx->markProhibited($ctx->message()),
            'accepted' => $ctx->setBase('fc.accepted('.$ctx->message().')'),
            'declined' => $ctx->setBase('fc.declined('.$ctx->message().')'),
            default => null,
        };
    }
}
