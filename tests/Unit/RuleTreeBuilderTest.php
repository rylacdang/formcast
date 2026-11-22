<?php

declare(strict_types=1);

use Rylacdang\Formcast\Rules\NormalizedRule;
use Rylacdang\Formcast\Rules\RuleNormalizer;
use Rylacdang\Formcast\Tree\RuleTreeBuilder;

it('builds nested object nodes from dot-notation keys and creates implicit parents', function (): void {
    $normalizer = new RuleNormalizer;
    $builder = new RuleTreeBuilder;

    $tree = $builder->build($normalizer->normalizeAll([
        'meta.address.city' => ['required', 'string'],
        'meta.address.zip' => ['nullable', 'string'],
        'customer_email' => ['required', 'email'],
    ]));

    expect(array_keys($tree->children))->toBe(['customer_email', 'meta']);

    $email = $tree->child('customer_email');
    expect($email)->not->toBeNull();
    expect($email?->isLeaf())->toBeTrue();
    expect($email?->implicit)->toBeFalse();

    $meta = $tree->child('meta');
    expect($meta?->isObject())->toBeTrue();
    expect($meta?->implicit)->toBeTrue();
    expect($meta?->path)->toBe('meta');

    $address = $meta?->child('address');
    expect($address?->isObject())->toBeTrue();
    expect($address?->implicit)->toBeTrue();
    expect($address?->path)->toBe('meta.address');
    expect(array_keys($address?->children ?? []))->toBe(['city', 'zip']);

    $city = $address?->child('city');
    expect($city?->path)->toBe('meta.address.city');
    expect($city?->implicit)->toBeFalse();
    expect($city?->rules)->toEqual([new NormalizedRule('required'), new NormalizedRule('string')]);
});

it('builds array-of-scalars and array-of-objects nodes from wildcard keys', function (): void {
    $normalizer = new RuleNormalizer;
    $builder = new RuleTreeBuilder;

    $tree = $builder->build($normalizer->normalizeAll([
        'items.*.quantity' => ['required', 'integer', 'min:1'],
        'items.*.product_id' => ['required', 'integer'],
        'items' => ['required', 'array', 'min:1'],
        'tags.*' => ['required', 'string', 'max:30'],
    ]));

    expect(array_keys($tree->children))->toBe(['items', 'tags']);

    $items = $tree->child('items');
    expect($items?->isArray())->toBeTrue();
    expect($items?->implicit)->toBeFalse();
    expect($items?->rules)->toHaveCount(3);
    expect($items?->element?->path)->toBe('items.*');
    expect($items?->element?->isObject())->toBeTrue();
    expect(array_keys($items?->element?->children ?? []))->toBe(['product_id', 'quantity']);
    expect($items?->element?->child('product_id')?->path)->toBe('items.*.product_id');

    $tags = $tree->child('tags');
    expect($tags?->isArray())->toBeTrue();
    expect($tags?->implicit)->toBeTrue();
    expect($tags?->element?->isLeaf())->toBeTrue();
    expect($tags?->element?->path)->toBe('tags.*');
    expect($tags?->element?->rules)->toHaveCount(3);
});

it('handles deeply nested wildcards like orders.*.items.*.sku and matrix.*.*', function (): void {
    $normalizer = new RuleNormalizer;
    $builder = new RuleTreeBuilder;

    $tree = $builder->build($normalizer->normalizeAll([
        'orders.*.items.*.sku' => ['required', 'string'],
        'matrix.*.*' => ['numeric'],
    ]));

    $sku = $tree->child('orders')?->element?->child('items')?->element?->child('sku');
    expect($sku)->not->toBeNull();
    expect($sku?->path)->toBe('orders.*.items.*.sku');
    expect($sku?->isLeaf())->toBeTrue();

    $cell = $tree->child('matrix')?->element?->element;
    expect($cell)->not->toBeNull();
    expect($cell?->path)->toBe('matrix.*.*');
    expect($cell?->rules)->toEqual([new NormalizedRule('numeric')]);
});
