<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Emitters\Mappers;

use BackedEnum;
use ReflectionClass;
use Rylacdang\Formcast\Contracts\RuleMapper;
use Rylacdang\Formcast\Emitters\ZodContext;
use Rylacdang\Formcast\Rules\NormalizedRule;
use UnitEnum;

final class SetMappers implements RuleMapper
{
    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'in',
            'not_in',
            'enum',
        ];
    }

    public function map(NormalizedRule $rule, ZodContext $ctx): void
    {
        match ($rule->name) {
            'in' => $this->mapIn($rule->parameters, $ctx),
            'not_in' => $this->mapNotIn($rule->parameters, $ctx),
            'enum' => $this->mapEnum($rule, $ctx),
            default => null,
        };
    }

    /**
     * @param  list<string>  $values
     */
    private function mapIn(array $values, ZodContext $ctx): void
    {
        $quoted = implode(', ', array_map($ctx->quote(...), $values));
        $ctx->setBase('fc.in(['.$quoted.'], '.$ctx->message().')');
    }

    /**
     * @param  list<string>  $values
     */
    private function mapNotIn(array $values, ZodContext $ctx): void
    {
        $quoted = implode(', ', array_map($ctx->quote(...), $values));
        $ctx->chain('refine', 'fc.notIn(['.$quoted.'])', $ctx->message());
    }

    private function mapEnum(NormalizedRule $rule, ZodContext $ctx): void
    {
        $enumClass = $rule->parameters[0] ?? '';

        if ($enumClass === '' || ! enum_exists($enumClass)) {
            return;
        }

        /** @var class-string<UnitEnum> $enumClass */
        $only = $this->parseConstraint($rule->parameters, 'only=');
        $except = $this->parseConstraint($rule->parameters, 'except=');

        if ($only === null && $except === null) {
            $ctx->addEnumImport($enumClass);
            $shortName = (new ReflectionClass($enumClass))->getShortName();
            $ctx->setBase('z.enum('.$shortName.', { message: '.$ctx->message().' })');

            return;
        }

        // Filtered enum (`only` / `except`): emit exact allowed backed values.
        $cases = $enumClass::cases();
        $allowed = [];

        foreach ($cases as $case) {
            $val = $case instanceof BackedEnum ? (string) $case->value : $case->name;

            if ($only !== null && ! in_array($val, $only, true)) {
                continue;
            }

            if ($except !== null && in_array($val, $except, true)) {
                continue;
            }

            $allowed[] = $val;
        }

        $quoted = implode(', ', array_map($ctx->quote(...), $allowed));
        $ctx->setBase('fc.in(['.$quoted.'], '.$ctx->message().')');
    }

    /**
     * @param  list<string>  $parameters
     * @return list<string>|null
     */
    private function parseConstraint(array $parameters, string $prefix): ?array
    {
        foreach (array_slice($parameters, 1) as $param) {
            if (str_starts_with($param, $prefix)) {
                $raw = substr($param, strlen($prefix));

                return $raw === '' ? [] : explode(',', $raw);
            }
        }

        return null;
    }
}
