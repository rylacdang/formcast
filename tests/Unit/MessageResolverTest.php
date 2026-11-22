<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use Rylacdang\Formcast\Messages\MessageResolver;
use Rylacdang\Formcast\Rules\FieldType;
use Rylacdang\Formcast\Rules\NormalizedRule;

it('resolves default laravel validation messages with type-specific size variants', function (): void {
    /** @var Translator $translator */
    $translator = $this->app->make('translator');
    $resolver = new MessageResolver($translator);

    expect($resolver->resolve('customer_email', new NormalizedRule('required'), FieldType::String))
        ->toBe('The customer email field is required.');

    expect($resolver->resolve('customer_email', new NormalizedRule('max', ['255']), FieldType::String))
        ->toBe('The customer email field must not be greater than 255 characters.');

    expect($resolver->resolve('items.*.quantity', new NormalizedRule('max', ['99']), FieldType::Integer))
        ->toBe('The items.*.quantity field must not be greater than 99.');

    expect($resolver->resolve('items', new NormalizedRule('min', ['1']), FieldType::Array))
        ->toBe('The items field must have at least 1 items.');

    expect($resolver->resolve('avatar', new NormalizedRule('max', ['2048']), FieldType::File))
        ->toBe('The avatar field must not be greater than 2048 kilobytes.');
});

it('resolves messages in precedence order: form request messages, lang custom, then default', function (): void {
    /** @var Illuminate\Translation\Translator $translator */
    $translator = $this->app->make('translator');
    $translator->load('*', 'validation', 'en');
    $translator->addLines([
        'validation.custom.email.required' => 'Custom lang line for :attribute.',
    ], 'en');

    $resolver = new MessageResolver($translator);

    // 1. FormRequest exact message wins over lang custom
    expect($resolver->resolve(
        field: 'email',
        rule: new NormalizedRule('required'),
        customMessages: ['email.required' => 'FormRequest says :attribute is needed.'],
    ))->toBe('FormRequest says email is needed.');

    // 2. Wildcard FormRequest message matches
    expect($resolver->resolve(
        field: 'items.*.quantity',
        rule: new NormalizedRule('min', ['1']),
        fieldType: FieldType::Integer,
        customMessages: ['items.*.quantity.min' => 'Item :position needs at least :min.'],
    ))->toBe('Item :position needs at least 1.');

    // 3. Lang custom wins over default when no FormRequest override exists
    expect($resolver->resolve(
        field: 'email',
        rule: new NormalizedRule('required'),
    ))->toBe('Custom lang line for email.');
});

it('resolves attributes from form request, lang attributes, and snake-to-words fallback', function (): void {
    /** @var Illuminate\Translation\Translator $translator */
    $translator = $this->app->make('translator');
    $translator->load('*', 'validation', 'en');
    $translator->addLines([
        'validation.attributes.payment_method' => 'payment method',
    ], 'en');

    $resolver = new MessageResolver($translator);

    $message = $resolver->resolve(
        field: 'gcash_reference',
        rule: new NormalizedRule('required_if', ['payment_method', 'gcash']),
        fieldType: FieldType::String,
        customAttributes: ['gcash_reference' => 'GCash reference number'],
    );

    expect($message)->toBe('The GCash reference number field is required when payment method is gcash.');

    expect($resolver->resolve(
        field: 'gcash_reference',
        rule: new NormalizedRule('size', ['13']),
        fieldType: FieldType::String,
        customAttributes: ['gcash_reference' => 'GCash reference number'],
    ))->toBe('The GCash reference number field must be 13 characters.');
});

it('defers :index and :position placeholders for runtime wildcard replacement', function (): void {
    /** @var Translator $translator */
    $translator = $this->app->make('translator');
    $resolver = new MessageResolver($translator);

    $message = $resolver->resolve(
        field: 'items.*.sku',
        rule: new NormalizedRule('required'),
        customMessages: ['items.*.sku.required' => 'Row :position (index :index) requires a SKU.'],
    );

    expect($message)->toBe('Row :position (index :index) requires a SKU.');
});
