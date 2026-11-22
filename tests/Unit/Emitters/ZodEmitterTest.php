<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;
use Rylacdang\Formcast\Contracts\CastsToZod;
use Rylacdang\Formcast\Emitters\ZodContext;
use Rylacdang\Formcast\Emitters\ZodEmitter;
use Rylacdang\Formcast\Formcast;
use Rylacdang\Formcast\Messages\MessageResolver;
use Rylacdang\Formcast\Rules\RuleClassifier;
use Rylacdang\Formcast\Rules\RuleNormalizer;
use Rylacdang\Formcast\Tree\RuleTreeBuilder;

enum EmitterPaymentMethod: string
{
    case Cash = 'cash';
    case Gcash = 'gcash';
    case Card = 'card';
}

function makeEmitter(?Formcast $registry = null): ZodEmitter
{
    /** @var Translator $translator */
    $translator = app('translator');

    return new ZodEmitter(
        messageResolver: new MessageResolver($translator),
        classifier: new RuleClassifier(registry: $registry),
        registry: $registry,
    );
}

it('emits presence and type rules deterministically', function (): void {
    $normalizer = new RuleNormalizer;
    $tree = (new RuleTreeBuilder)->build($normalizer->normalizeAll([
        'name' => ['required', 'string', 'max:100'],
        'age' => ['nullable', 'integer', 'min:18', 'max:120'],
        'price' => ['required', 'numeric', 'min:0'],
        'active' => ['required', 'boolean'],
        'terms' => ['accepted'],
        'secret' => ['prohibited'],
    ]));

    $result = makeEmitter()->emit($tree);

    expect($result->schema)->toMatchSnapshot();
    expect($result->serverFields)->toBe([]);
});

it('emits format, set, enum, and date rules with enum imports', function (): void {
    $normalizer = new RuleNormalizer;
    $tree = (new RuleTreeBuilder)->build($normalizer->normalizeAll([
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'website' => ['nullable', 'url'],
        'code' => ['required', 'string', 'alpha_dash', 'starts_with:ORD-,INV-'],
        'status' => ['required', Rule::in(['draft', 'published'])],
        'payment_method' => ['required', Rule::enum(EmitterPaymentMethod::class)],
        'publish_at' => ['nullable', 'date', 'after:today'],
    ]));

    $result = makeEmitter()->emit($tree);

    expect($result->schema)->toMatchSnapshot();
    expect($result->serverFields)->toBe(['email']);
    expect($result->enumClasses)->toBe([EmitterPaymentMethod::class]);
});

it('emits file, password, and nested wildcard rules', function (): void {
    $normalizer = new RuleNormalizer;
    $tree = (new RuleTreeBuilder)->build($normalizer->normalizeAll([
        'avatar' => ['required', File::image()->max(2048)],
        'password' => ['required', Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()],
        'items' => ['required', 'array', 'min:1'],
        'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
        'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
    ]));

    $result = makeEmitter()->emit($tree);

    expect($result->schema)->toMatchSnapshot();
    expect($result->serverFields)->toBe(['items.*.product_id', 'password']);
});

it('supports CastsToZod rule objects and Formcast::extend custom mappers', function (): void {
    $registry = new Formcast;
    $registry->extend('ph_mobile', function (ZodContext $ctx): void {
        $ctx->chain('regex', '/^(09|\+639)\d{9}$/', $ctx->message('regex'));
    });

    $customRule = new class implements CastsToZod, ValidationRule
    {
        public function validate(string $attribute, mixed $value, Closure $fail): void {}

        public function toZod(ZodContext $ctx): string
        {
            return $ctx->chain('regex', '/^SKU-\d{4}$/', $ctx->message('regex'));
        }
    };

    $normalizer = new RuleNormalizer;
    $tree = (new RuleTreeBuilder)->build($normalizer->normalizeAll([
        'mobile' => ['required', 'string', 'ph_mobile'],
        'sku' => ['required', 'string', $customRule],
    ]));

    $result = makeEmitter($registry)->emit($tree);

    expect($result->schema)->toMatchSnapshot();
    expect($result->serverFields)->toBe([]);
});
