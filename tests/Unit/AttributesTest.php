<?php

declare(strict_types=1);

use Rylacdang\Formcast\Attributes\Formcast;
use Rylacdang\Formcast\Attributes\FormcastIgnore;

it('marks formcast as a class-only attribute with an optional export name', function (): void {
    $reflection = new ReflectionClass(Formcast::class);
    $attributes = $reflection->getAttributes(Attribute::class);

    expect($attributes)->toHaveCount(1);
    expect($attributes[0]->newInstance()->flags)->toBe(Attribute::TARGET_CLASS);

    $default = new Formcast;
    $named = new Formcast(name: 'StoreOrder');

    expect($default->name)->toBeNull();
    expect($named->name)->toBe('StoreOrder');
});

it('marks formcast ignore as a class-only attribute', function (): void {
    $reflection = new ReflectionClass(FormcastIgnore::class);
    $attributes = $reflection->getAttributes(Attribute::class);

    expect($attributes)->toHaveCount(1);
    expect($attributes[0]->newInstance()->flags)->toBe(Attribute::TARGET_CLASS);
});
