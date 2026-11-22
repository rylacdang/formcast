<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Emitters\Mappers;

use Rylacdang\Formcast\Contracts\RuleMapper;
use Rylacdang\Formcast\Emitters\ZodContext;
use Rylacdang\Formcast\Rules\NormalizedRule;

final class CrossFieldMappers implements RuleMapper
{
    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'required_if',
            'required_unless',
            'required_if_accepted',
            'required_with',
            'required_with_all',
            'required_without',
            'required_without_all',
            'prohibited_if',
            'prohibited_unless',
            'missing_if',
            'accepted_if',
            'declined_if',
            'confirmed',
            'same',
            'different',
            'in_array',
            'gt',
            'gte',
            'lt',
            'lte',
        ];
    }

    public function map(NormalizedRule $rule, ZodContext $ctx): void
    {
        match ($rule->name) {
            'required_if' => $this->mapOtherValues('requiredIf', $rule->parameters, $ctx),
            'required_unless' => $this->mapOtherValues('requiredUnless', $rule->parameters, $ctx),
            'prohibited_if' => $this->mapOtherValues('prohibitedIf', $rule->parameters, $ctx),
            'prohibited_unless' => $this->mapOtherValues('prohibitedUnless', $rule->parameters, $ctx),
            'missing_if' => $this->mapOtherValues('missingIf', $rule->parameters, $ctx),
            'accepted_if' => $this->mapOtherValues('acceptedIf', $rule->parameters, $ctx),
            'declined_if' => $this->mapOtherValues('declinedIf', $rule->parameters, $ctx),
            'required_if_accepted' => $this->mapSingleOther('requiredIfAccepted', $rule->parameters, $ctx),
            'required_with' => $this->mapMultipleOthers('requiredWith', $rule->parameters, $ctx),
            'required_with_all' => $this->mapMultipleOthers('requiredWithAll', $rule->parameters, $ctx),
            'required_without' => $this->mapMultipleOthers('requiredWithout', $rule->parameters, $ctx),
            'required_without_all' => $this->mapMultipleOthers('requiredWithoutAll', $rule->parameters, $ctx),
            'confirmed' => $this->mapConfirmed($rule->parameters, $ctx),
            'same' => $this->mapSingleOther('same', $rule->parameters, $ctx),
            'different' => $this->mapSingleOther('different', $rule->parameters, $ctx),
            'in_array' => $this->mapSingleOther('inArray', $rule->parameters, $ctx),
            'gt', 'gte', 'lt', 'lte' => $this->mapComparison($rule->name, $rule->parameters, $ctx),
            default => null,
        };
    }

    /**
     * @param  list<string>  $params
     */
    private function mapOtherValues(string $helper, array $params, ZodContext $ctx): void
    {
        $other = $params[0] ?? '';

        if ($other === '') {
            return;
        }

        $values = array_slice($params, 1);
        $quotedValues = implode(', ', array_map($ctx->quote(...), $values));

        $ctx->addSuperRefine(sprintf(
            'fc.%s(%s, %s, [%s], %s)',
            $helper,
            $ctx->quote($ctx->field),
            $ctx->quote($other),
            $quotedValues,
            $ctx->message(),
        ));
        $ctx->addDependency($other, $ctx->field);
    }

    /**
     * @param  list<string>  $params
     */
    private function mapSingleOther(string $helper, array $params, ZodContext $ctx): void
    {
        $other = $params[0] ?? '';

        if ($other === '') {
            return;
        }

        $ctx->addSuperRefine(sprintf(
            'fc.%s(%s, %s, %s)',
            $helper,
            $ctx->quote($ctx->field),
            $ctx->quote($other),
            $ctx->message(),
        ));
        $ctx->addDependency($other, $ctx->field);
    }

    /**
     * @param  list<string>  $params
     */
    private function mapMultipleOthers(string $helper, array $params, ZodContext $ctx): void
    {
        if ($params === []) {
            return;
        }

        $quotedOthers = implode(', ', array_map($ctx->quote(...), $params));

        $ctx->addSuperRefine(sprintf(
            'fc.%s(%s, [%s], %s)',
            $helper,
            $ctx->quote($ctx->field),
            $quotedOthers,
            $ctx->message(),
        ));

        foreach ($params as $other) {
            $ctx->addDependency($other, $ctx->field);
        }
    }

    /**
     * @param  list<string>  $params
     */
    private function mapConfirmed(array $params, ZodContext $ctx): void
    {
        $confirmationField = $params[0] ?? $ctx->field.'_confirmation';

        $ctx->addConfirmationField($confirmationField);
        $ctx->addSuperRefine(sprintf(
            'fc.confirmed(%s, %s, %s)',
            $ctx->quote($ctx->field),
            $ctx->quote($confirmationField),
            $ctx->message(),
        ));
        $ctx->addDependency($confirmationField, $ctx->field);
        $ctx->addDependency($ctx->field, $confirmationField);
    }

    /**
     * @param  list<string>  $params
     */
    private function mapComparison(string $operator, array $params, ZodContext $ctx): void
    {
        $other = $params[0] ?? '';

        if ($other === '') {
            return;
        }

        $ctx->addSuperRefine(sprintf(
            'fc.compare(%s, %s, %s, %s, %s)',
            $ctx->quote($ctx->field),
            $ctx->quote($operator),
            $ctx->quote($other),
            $ctx->quote($ctx->fieldType->sizeVariant()),
            $ctx->message(),
        ));
        $ctx->addDependency($other, $ctx->field);
    }
}
