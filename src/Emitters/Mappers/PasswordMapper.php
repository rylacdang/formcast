<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Emitters\Mappers;

use Rylacdang\Formcast\Contracts\RuleMapper;
use Rylacdang\Formcast\Emitters\ZodContext;
use Rylacdang\Formcast\Rules\NormalizedRule;

final class PasswordMapper implements RuleMapper
{
    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['password'];
    }

    public function map(NormalizedRule $rule, ZodContext $ctx): void
    {
        if (! $ctx->hasExplicitBase()) {
            $ctx->setBase('fc.string()');
        }

        foreach ($rule->parameters as $param) {
            if (str_starts_with($param, 'min=')) {
                $min = substr($param, 4);
                $msg = $ctx->message('min', new NormalizedRule('min', [$min]));
                $ctx->chain('min', $min, $msg);

                continue;
            }

            if (str_starts_with($param, 'max=')) {
                $max = substr($param, 4);
                $msg = $ctx->message('max', new NormalizedRule('max', [$max]));
                $ctx->chain('max', $max, $msg);

                continue;
            }

            match ($param) {
                'letters' => $ctx->chain('regex', '/\p{L}/u', $ctx->message('password.letters', new NormalizedRule('password.letters'))),
                'mixed_case' => $ctx->chain('refine', 'fc.isMixedCase', $ctx->message('password.mixed', new NormalizedRule('password.mixed'))),
                'numbers' => $ctx->chain('regex', '/\p{N}/u', $ctx->message('password.numbers', new NormalizedRule('password.numbers'))),
                'symbols' => $ctx->chain('regex', '/[\p{Z}\p{S}\p{P}]/u', $ctx->message('password.symbols', new NormalizedRule('password.symbols'))),
                default => null,
            };
        }
    }
}
