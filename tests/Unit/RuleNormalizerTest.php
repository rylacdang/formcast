<?php

declare(strict_types=1);

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;
use Rylacdang\Formcast\Rules\NormalizedRule;
use Rylacdang\Formcast\Rules\RuleNormalizer;

enum TestPaymentMethod: string
{
    case Cash = 'cash';
    case Gcash = 'gcash';
    case Card = 'card';
}

it('normalizes pipe-delimited string rules and csv parameters', function (): void {
    $normalizer = new RuleNormalizer;

    $rules = $normalizer->normalizeField('required|string|max:255|in:foo,"bar,baz"');

    expect($rules)->toEqual([
        new NormalizedRule('required'),
        new NormalizedRule('string'),
        new NormalizedRule('max', ['255']),
        new NormalizedRule('in', ['foo', 'bar,baz']),
    ]);
});

it('keeps regex patterns intact when they contain pipe characters or commas', function (): void {
    $normalizer = new RuleNormalizer;

    $fromArray = $normalizer->normalizeField(['required', 'regex:/^(09|\+639)\d{9,10}$/i']);
    expect($fromArray)->toEqual([
        new NormalizedRule('required'),
        new NormalizedRule('regex', ['/^(09|\+639)\d{9,10}$/i']),
    ]);

    $fromPipe = $normalizer->normalizeField('required|regex:/^(a|b){1,3}$/|max:10');
    expect($fromPipe)->toEqual([
        new NormalizedRule('required'),
        new NormalizedRule('regex', ['/^(a|b){1,3}$/']),
        new NormalizedRule('max', ['10']),
    ]);
});

it('normalizes Rule::in, Rule::notIn, Rule::unique, and Rule::exists', function (): void {
    $normalizer = new RuleNormalizer;

    $rules = $normalizer->normalizeField([
        Rule::in(['pending', 'paid,verified']),
        Rule::notIn(['deleted']),
        Rule::unique('orders', 'email'),
        Rule::exists('products', 'id'),
    ]);

    expect($rules[0])->toEqual(new NormalizedRule('in', ['pending', 'paid,verified']));
    expect($rules[1])->toEqual(new NormalizedRule('not_in', ['deleted']));
    expect($rules[2]->name)->toBe('unique');
    expect($rules[2]->forceServer)->toBeTrue();
    expect($rules[3]->name)->toBe('exists');
    expect($rules[3]->forceServer)->toBeTrue();
});

it('normalizes Rule::enum including only and except constraints', function (): void {
    $normalizer = new RuleNormalizer;

    $rules = $normalizer->normalizeField([
        Rule::enum(TestPaymentMethod::class)->only([TestPaymentMethod::Cash, TestPaymentMethod::Gcash]),
        Rule::enum(TestPaymentMethod::class)->except(TestPaymentMethod::Card),
    ]);

    expect($rules[0]->name)->toBe('enum');
    expect($rules[0]->parameters)->toBe([TestPaymentMethod::class, 'only=cash,gcash']);
    expect($rules[0]->forceServer)->toBeFalse();

    expect($rules[1]->name)->toBe('enum');
    expect($rules[1]->parameters)->toBe([TestPaymentMethod::class, 'except=card']);
});

it('normalizes Password rules and flags uncompromised as server-only', function (): void {
    $normalizer = new RuleNormalizer;

    $rules = $normalizer->normalizeField([
        Password::min(10)->max(64)->letters()->mixedCase()->numbers()->symbols()->uncompromised(),
    ]);

    expect($rules)->toHaveCount(2);
    expect($rules[0]->name)->toBe('password');
    expect($rules[0]->parameters)->toBe(['min=10', 'max=64', 'letters', 'mixed_case', 'numbers', 'symbols']);
    expect($rules[0]->forceServer)->toBeFalse();

    expect($rules[1]->name)->toBe('password_uncompromised');
    expect($rules[1]->forceServer)->toBeTrue();
});

it('normalizes File and image rules including dimensions as server-only', function (): void {
    $normalizer = new RuleNormalizer;

    $rules = $normalizer->normalizeField([
        File::image()->min(10)->max(2048)->dimensions(Rule::dimensions()->maxWidth(1000)),
    ]);

    $names = array_map(fn (NormalizedRule $r): string => $r->name, $rules);

    expect($names)->toContain('file', 'image', 'between', 'dimensions');

    $dimensions = array_values(array_filter($rules, fn (NormalizedRule $r): bool => $r->name === 'dimensions'));
    expect($dimensions[0]->forceServer)->toBeTrue();
});

it('normalizes Rule::requiredIf and Rule::when for booleans and closures', function (): void {
    $normalizer = new RuleNormalizer;

    expect($normalizer->normalizeField([Rule::requiredIf(true)]))->toEqual([new NormalizedRule('required')]);
    expect($normalizer->normalizeField([Rule::requiredIf(false)]))->toBe([]);

    $closureRequired = $normalizer->normalizeField([Rule::requiredIf(fn (): bool => true)]);
    expect($closureRequired)->toHaveCount(1);
    expect($closureRequired[0]->name)->toBe('required_if');
    expect($closureRequired[0]->forceServer)->toBeTrue();

    expect($normalizer->normalizeField([Rule::when(true, ['string', 'max:50'], ['integer'])]))->toEqual([
        new NormalizedRule('string'),
        new NormalizedRule('max', ['50']),
    ]);

    expect($normalizer->normalizeField([Rule::when(false, ['string'], ['integer'])]))->toEqual([
        new NormalizedRule('integer'),
    ]);

    $closureWhen = $normalizer->normalizeField([Rule::when(fn (): bool => true, ['string'])]);
    expect($closureWhen[0]->forceServer)->toBeTrue();
});

it('marks closures and opaque ValidationRule objects as server-only while preserving source', function (): void {
    $normalizer = new RuleNormalizer;

    $customRule = new class implements ValidationRule
    {
        public function validate(string $attribute, mixed $value, Closure $fail): void {}
    };

    $rules = $normalizer->normalizeField([
        fn (string $attr, mixed $val, Closure $fail): null => null,
        $customRule,
    ]);

    expect($rules)->toHaveCount(2);
    expect($rules[0]->name)->toBe('closure');
    expect($rules[0]->forceServer)->toBeTrue();
    expect($rules[0]->source)->toBeInstanceOf(Closure::class);

    expect($rules[1]->name)->toBe($customRule::class);
    expect($rules[1]->forceServer)->toBeTrue();
    expect($rules[1]->source)->toBe($customRule);
});
