<?php

declare(strict_types=1);

use Rylacdang\Formcast\Rules\FieldType;
use Rylacdang\Formcast\Rules\RuleNormalizer;
use Rylacdang\Formcast\Rules\TypeInferrer;

it('infers field types following laravel getSize precedence', function (): void {
    $normalizer = new RuleNormalizer;
    $inferrer = new TypeInferrer;

    expect($inferrer->infer($normalizer->normalizeField('required|integer|min:1')))
        ->toBe(FieldType::Integer);
    expect($inferrer->infer($normalizer->normalizeField('required|numeric|min:0.5')))
        ->toBe(FieldType::Numeric);
    expect($inferrer->infer($normalizer->normalizeField('required|decimal:2')))
        ->toBe(FieldType::Numeric);
    expect($inferrer->infer($normalizer->normalizeField('required|file|max:2048')))
        ->toBe(FieldType::File);
    expect($inferrer->infer($normalizer->normalizeField('required|array|min:1')))
        ->toBe(FieldType::Array);
    expect($inferrer->infer($normalizer->normalizeField('required|boolean')))
        ->toBe(FieldType::Boolean);
    expect($inferrer->infer($normalizer->normalizeField('required|date|after:today')))
        ->toBe(FieldType::Date);
    expect($inferrer->infer($normalizer->normalizeField('required|email|max:255')))
        ->toBe(FieldType::String);
    expect($inferrer->infer($normalizer->normalizeField('required|max:10')))
        ->toBe(FieldType::Mixed);
});

it('maps field types to laravel validation size variants', function (): void {
    expect(FieldType::Integer->sizeVariant())->toBe('numeric');
    expect(FieldType::Numeric->sizeVariant())->toBe('numeric');
    expect(FieldType::Array->sizeVariant())->toBe('array');
    expect(FieldType::File->sizeVariant())->toBe('file');
    expect(FieldType::String->sizeVariant())->toBe('string');
    expect(FieldType::Mixed->sizeVariant())->toBe('string');
});
