<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Emitters\Mappers;

use Rylacdang\Formcast\Contracts\RuleMapper;
use Rylacdang\Formcast\Emitters\ZodContext;
use Rylacdang\Formcast\Rules\NormalizedRule;
use Rylacdang\Formcast\Rules\RegexTranslator;

final class FormatMappers implements RuleMapper
{
    public function __construct(
        private readonly RegexTranslator $regexTranslator = new RegexTranslator,
    ) {}

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
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
        ];
    }

    public function map(NormalizedRule $rule, ZodContext $ctx): void
    {
        match ($rule->name) {
            'email' => $ctx->setBase('fc.email('.$ctx->message().')'),
            'url' => $ctx->chain('url', $ctx->message()),
            'uuid' => $ctx->chain('uuid', $ctx->message()),
            'ulid' => $ctx->chain('ulid', $ctx->message()),
            'ip' => $ctx->chain('refine', 'fc.isIp', $ctx->message()),
            'ipv4' => $ctx->chain('refine', 'fc.isIpv4', $ctx->message()),
            'ipv6' => $ctx->chain('refine', 'fc.isIpv6', $ctx->message()),
            'mac_address' => $ctx->chain('regex', '/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $ctx->message()),
            'hex_color' => $ctx->chain('regex', '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $ctx->message()),
            'alpha' => $ctx->chain('regex', '/^\p{L}+$/u', $ctx->message()),
            'alpha_num' => $ctx->chain('regex', '/^[\p{L}\p{N}]+$/u', $ctx->message()),
            'alpha_dash' => $ctx->chain('regex', '/^[\p{L}\p{N}_-]+$/u', $ctx->message()),
            'ascii' => $ctx->chain('regex', '/^[\x00-\x7F]*$/', $ctx->message()),
            'lowercase' => $ctx->chain('refine', '(v) => v === v.toLowerCase()', $ctx->message()),
            'uppercase' => $ctx->chain('refine', '(v) => v === v.toUpperCase()', $ctx->message()),
            'starts_with' => $this->mapAffix('startsWith', $rule->parameters, $ctx),
            'ends_with' => $this->mapAffix('endsWith', $rule->parameters, $ctx),
            'doesnt_start_with' => $this->mapAffix('doesntStartWith', $rule->parameters, $ctx),
            'doesnt_end_with' => $this->mapAffix('doesntEndWith', $rule->parameters, $ctx),
            'regex' => $this->mapRegex($rule, $ctx, negate: false),
            'not_regex' => $this->mapRegex($rule, $ctx, negate: true),
            default => null,
        };
    }

    /**
     * @param  list<string>  $values
     */
    private function mapAffix(string $helper, array $values, ZodContext $ctx): void
    {
        $quoted = implode(', ', array_map($ctx->quote(...), $values));
        $ctx->chain('refine', 'fc.'.$helper.'(['.$quoted.'])', $ctx->message());
    }

    private function mapRegex(NormalizedRule $rule, ZodContext $ctx, bool $negate): void
    {
        $jsRegex = $this->regexTranslator->translate($rule->parameters[0] ?? '');

        if ($jsRegex === null) {
            return;
        }

        if ($negate) {
            $ctx->chain('refine', '(v) => !'.$jsRegex.'.test(String(v))', $ctx->message());

            return;
        }

        $ctx->chain('regex', $jsRegex, $ctx->message());
    }
}
