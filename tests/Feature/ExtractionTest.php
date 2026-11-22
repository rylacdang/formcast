<?php

declare(strict_types=1);

use Rylacdang\Formcast\Discovery\DiscoveredRequest;
use Rylacdang\Formcast\Extraction\FormRequestExtractor;
use Rylacdang\Formcast\Extraction\GenerationContext;
use Rylacdang\Formcast\Tests\Fixtures\Extraction\ContextDependentRequest;
use Rylacdang\Formcast\Tests\Fixtures\Extraction\FailingRequest;
use Rylacdang\Formcast\Tests\Fixtures\Extraction\OverrideHookRequest;
use Rylacdang\Formcast\Tests\Fixtures\Extraction\PrepareAndInjectedRequest;
use Rylacdang\Formcast\Tests\Fixtures\Extraction\StandardRequest;

it('extracts rules, messages, and attributes without triggering validation or authorization', function (): void {
    $extractor = new FormRequestExtractor($this->app);

    $extracted = $extractor->extract(new DiscoveredRequest(
        class: StandardRequest::class,
        filePath: __DIR__.'/../Fixtures/Extraction/StandardRequest.php',
        exportName: 'StandardRequest',
    ));

    expect($extracted->class)->toBe(StandardRequest::class);
    expect($extracted->exportName)->toBe('StandardRequest');
    expect($extracted->rules)->toBe([
        'email' => ['required', 'email', 'max:255'],
        'age' => ['nullable', 'integer', 'min:18'],
    ]);
    expect($extracted->messages)->toBe(['email.required' => 'We need your email address.']);
    expect($extracted->attributes)->toBe(['email' => 'email address']);
    expect($extracted->hasPrepareForValidation)->toBeFalse();
    expect($extracted->usedFormcastRules)->toBeFalse();
});

it('prefers formcastRules over rules when defined', function (): void {
    $extractor = new FormRequestExtractor($this->app);

    $extracted = $extractor->extract(new DiscoveredRequest(
        class: OverrideHookRequest::class,
        filePath: __DIR__.'/../Fixtures/Extraction/OverrideHookRequest.php',
        exportName: 'OverrideHookRequest',
    ));

    expect($extracted->rules)->toBe(['name' => ['required', 'string', 'max:100']]);
    expect($extracted->usedFormcastRules)->toBeTrue();
});

it('supplies user and route parameters via GenerationContext', function (): void {
    $extractor = new FormRequestExtractor($this->app);
    $discovered = new DiscoveredRequest(
        class: ContextDependentRequest::class,
        filePath: __DIR__.'/../Fixtures/Extraction/ContextDependentRequest.php',
        exportName: 'ContextDependentRequest',
    );

    $default = $extractor->extract($discovered);
    expect($default->rules['email'])->toBe(['required', 'email', 'unique:users,email,NULL']);
    expect($default->rules['reference'])->toBe(['nullable', 'string', 'max:25']);

    $withContext = $extractor->extract(
        $discovered,
        new GenerationContext(user: (object) ['id' => 42], routeParameters: ['order' => 99]),
    );
    expect($withContext->rules['email'])->toBe(['required', 'email', 'unique:users,email,42']);
    expect($withContext->rules['reference'])->toBe(['nullable', 'string', 'max:50']);
});

it('detects prepareForValidation overrides and resolves method dependencies through the container', function (): void {
    $extractor = new FormRequestExtractor($this->app);

    $extracted = $extractor->extract(new DiscoveredRequest(
        class: PrepareAndInjectedRequest::class,
        filePath: __DIR__.'/../Fixtures/Extraction/PrepareAndInjectedRequest.php',
        exportName: 'PrepareAndInjectedRequest',
    ));

    expect($extracted->hasPrepareForValidation)->toBeTrue();
    expect($extracted->rules['has_registry'])->toBe(['boolean']);
});

it('skips failing requests with a descriptive warning while still extracting valid ones', function (): void {
    $extractor = new FormRequestExtractor($this->app);

    $result = $extractor->extractAll([
        new DiscoveredRequest(
            class: StandardRequest::class,
            filePath: __DIR__.'/../Fixtures/Extraction/StandardRequest.php',
            exportName: 'StandardRequest',
        ),
        new DiscoveredRequest(
            class: FailingRequest::class,
            filePath: __DIR__.'/../Fixtures/Extraction/FailingRequest.php',
            exportName: 'FailingRequest',
        ),
    ]);

    expect($result['extracted'])->toHaveCount(1);
    expect($result['extracted'][0]->class)->toBe(StandardRequest::class);
    expect($result['skipped'])->toHaveKey(FailingRequest::class);
    expect($result['skipped'][FailingRequest::class])->toContain('Missing tenant context');
});
