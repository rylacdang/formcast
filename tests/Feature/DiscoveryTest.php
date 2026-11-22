<?php

declare(strict_types=1);

use Rylacdang\Formcast\Discovery\DiscoveredRequest;
use Rylacdang\Formcast\Discovery\RequestDiscoverer;
use Rylacdang\Formcast\Tests\Fixtures\Discovery\AttributedRequest;
use Rylacdang\Formcast\Tests\Fixtures\Discovery\BasicRequest;
use Rylacdang\Formcast\Tests\Fixtures\Discovery\CustomNamedRequest;
use Rylacdang\Formcast\Tests\Fixtures\Discovery\Nested\SubdirRequest;

$fixturesPath = dirname(__DIR__).'/Fixtures/Discovery';

it('discovers all concrete form requests in all mode and skips ignored, abstract, and non-request classes', function () use ($fixturesPath): void {
    $discoverer = new RequestDiscoverer;

    $results = $discoverer->discover([$fixturesPath], mode: 'all');

    expect(array_map(fn (DiscoveredRequest $r): string => $r->class, $results))->toBe([
        AttributedRequest::class,
        BasicRequest::class,
        CustomNamedRequest::class,
        SubdirRequest::class,
    ]);

    expect(array_map(fn (DiscoveredRequest $r): string => $r->exportName, $results))->toBe([
        'AttributedRequest',
        'BasicRequest',
        'CustomOrder',
        'SubdirRequest',
    ]);
});

it('discovers only formcast-attributed requests in attribute mode and still honors ignore', function () use ($fixturesPath): void {
    $discoverer = new RequestDiscoverer;

    $results = $discoverer->discover([$fixturesPath], mode: 'attribute');

    expect(array_map(fn (DiscoveredRequest $r): string => $r->class, $results))->toBe([
        AttributedRequest::class,
        CustomNamedRequest::class,
    ]);
});

it('supports single file paths, deduplicates overlapping paths, and ignores missing paths', function () use ($fixturesPath): void {
    $discoverer = new RequestDiscoverer;

    $results = $discoverer->discover([
        $fixturesPath.'/BasicRequest.php',
        $fixturesPath.'/BasicRequest.php',
        $fixturesPath.'/DoesNotExist',
    ]);

    expect($results)->toHaveCount(1);
    expect($results[0]->class)->toBe(BasicRequest::class);
    expect($results[0]->filePath)->toEndWith('BasicRequest.php');
});
