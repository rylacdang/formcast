<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Workbench\App\Http\Controllers\OrderController;
use Workbench\App\Http\Requests\StoreOrderRequest;

beforeEach(function (): void {
    $this->outputDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'formcast-cmd-'.bin2hex(random_bytes(6));
    config()->set('formcast.output', $this->outputDir);
    config()->set('formcast.paths', [dirname(__DIR__, 2).'/workbench/app/Http/Requests']);
});

afterEach(function (): void {
    if (! is_dir($this->outputDir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->outputDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($this->outputDir);
});

it('generates casts, shared enums, barrel index, and route metadata end-to-end', function (): void {
    /** @var Router $router */
    $router = $this->app->make('router');
    $router->post('/orders', [OrderController::class, 'store'])->name('orders.store');

    $this->artisan('formcast:generate')
        ->expectsOutputToContain('Formcast generation complete.')
        ->assertSuccessful();

    $requestFile = $this->outputDir.'/Workbench/App/Http/Requests/StoreOrderRequest.ts';
    $enumFile = $this->outputDir.'/Workbench/App/Enums/PaymentMethod.ts';
    $barrelFile = $this->outputDir.'/index.ts';

    expect(is_file($requestFile))->toBeTrue();
    expect(is_file($enumFile))->toBeTrue();
    expect(is_file($barrelFile))->toBeTrue();

    $requestContents = (string) file_get_contents($requestFile);
    expect($requestContents)->toContain("import { PaymentMethod } from '../../Enums/PaymentMethod';");
    expect($requestContents)->toContain("serverFields: ['customer_email', 'items.*.product_id']");
    expect($requestContents)->toContain("payment_method: ['gcash_reference']");
    expect($requestContents)->toContain("uri: '/orders'");
    expect($requestContents)->toContain('The GCash reference number field is required when payment method is gcash.');
});

it('supports dry-run and check modes with non-zero exit when stale', function (): void {
    // Before generation, --check fails without writing anything
    $this->artisan('formcast:generate', ['--check' => true])
        ->expectsOutputToContain('Generated Formcast files are out of date.')
        ->assertFailed();

    expect(is_dir($this->outputDir))->toBeFalse();

    // --dry-run succeeds without touching disk
    $this->artisan('formcast:generate', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run complete')
        ->assertSuccessful();

    expect(is_dir($this->outputDir))->toBeFalse();

    // Real generation followed by --check succeeds
    $this->artisan('formcast:generate')->assertSuccessful();
    $this->artisan('formcast:generate', ['--check' => true])
        ->expectsOutputToContain('Formcast files are up to date.')
        ->assertSuccessful();

    // Tampering with a generated file causes --check to fail
    $requestFile = $this->outputDir.'/Workbench/App/Http/Requests/StoreOrderRequest.ts';
    file_put_contents($requestFile, "// tampered\n");

    $this->artisan('formcast:generate', ['--check' => true])
        ->assertFailed();
});

it('supports --path override and redacts configured fields', function (): void {
    config()->set('formcast.redact', [
        StoreOrderRequest::class => ['notes'],
    ]);

    $this->artisan('formcast:generate', [
        '--path' => [dirname(__DIR__, 2).'/workbench/app/Http/Requests/StoreOrderRequest.php'],
    ])->assertSuccessful();

    $requestFile = $this->outputDir.'/Workbench/App/Http/Requests/StoreOrderRequest.ts';
    $contents = (string) file_get_contents($requestFile);

    expect($contents)->toContain('customer_email:');
    expect($contents)->not->toContain('notes:');
});
