<?php

declare(strict_types=1);

use Rylacdang\Formcast\Formcast;
use Rylacdang\Formcast\Rules\NormalizedRule;
use Rylacdang\Formcast\Rules\RuleCategory;
use Rylacdang\Formcast\Rules\RuleClassifier;

it('classifies client, server, and meta rules accurately', function (): void {
    $classifier = new RuleClassifier;

    expect($classifier->classify(new NormalizedRule('required')))->toBe(RuleCategory::Client);
    expect($classifier->classify(new NormalizedRule('email', ['rfc'])))->toBe(RuleCategory::Client);
    expect($classifier->classify(new NormalizedRule('email', ['rfc', 'dns'])))->toBe(RuleCategory::Server);
    expect($classifier->classify(new NormalizedRule('unique', ['users,email'])))->toBe(RuleCategory::Server);
    expect($classifier->classify(new NormalizedRule('exists', ['products,id'])))->toBe(RuleCategory::Server);
    expect($classifier->classify(new NormalizedRule('current_password')))->toBe(RuleCategory::Server);
    expect($classifier->classify(new NormalizedRule('bail')))->toBe(RuleCategory::Meta);
    expect($classifier->classify(new NormalizedRule('exclude_if', ['type', 'guest'])))->toBe(RuleCategory::Meta);
});

it('falls back to server for unsafe regex patterns and allows safe ones on client', function (): void {
    $classifier = new RuleClassifier;

    expect($classifier->classify(new NormalizedRule('regex', ['/^[a-z]+$/'])))->toBe(RuleCategory::Client);
    expect($classifier->classify(new NormalizedRule('regex', ['/a++/'])))->toBe(RuleCategory::Server);
});

it('honors server_only config, custom registry mappers, and unsupported modes', function (): void {
    $registry = new Formcast;
    $registry->extend('ph_mobile', fn (): string => 'regex');

    $classifier = new RuleClassifier(
        registry: $registry,
        serverOnly: ['email'],
        unsupportedMode: 'server',
    );

    expect($classifier->classify(new NormalizedRule('email')))->toBe(RuleCategory::Server);
    expect($classifier->classify(new NormalizedRule('ph_mobile')))->toBe(RuleCategory::Client);
    expect($classifier->classify(new NormalizedRule('unknown_rule')))->toBe(RuleCategory::Server);
    expect($classifier->isUnsupported(new NormalizedRule('unknown_rule')))->toBeTrue();
    expect($classifier->isUnsupported(new NormalizedRule('unique')))->toBeFalse();

    $strictClassifier = new RuleClassifier(unsupportedMode: 'fail');
    expect(fn (): RuleCategory => $strictClassifier->classify(new NormalizedRule('unknown_rule')))
        ->toThrow(RuntimeException::class, 'Unsupported Formcast rule: unknown_rule');
});
