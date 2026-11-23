<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Rylacdang\Formcast\Discovery\RouteScanner;
use Rylacdang\Formcast\Tests\Fixtures\Discovery\BasicRequest;
use Rylacdang\Formcast\Tests\Fixtures\Discovery\CustomNamedRequest;

final class SampleOrderController
{
    public function store(BasicRequest $request): void {}

    public function update(int $id, BasicRequest $request): void {}

    public function index(): void {}
}

final class SampleInvokableController
{
    public function __invoke(CustomNamedRequest $request): void {}
}

it('maps FormRequests to controller actions, invokable controllers, uris, verbs, and route names', function (): void {
    /** @var Router $router */
    $router = $this->app->make('router');

    $router->post('/orders', [SampleOrderController::class, 'store'])->name('orders.store');
    $router->put('/orders/{id}', [SampleOrderController::class, 'update'])->name('orders.update');
    $router->get('/orders', [SampleOrderController::class, 'index'])->name('orders.index');
    $router->post('/checkout', SampleInvokableController::class)->name('checkout');
    $router->get('/health', fn () => 'ok');

    $scanner = new RouteScanner($router);
    $map = $scanner->scan();

    expect($map)->toBe([
        BasicRequest::class => [
            [
                'action' => SampleOrderController::class.'@store',
                'methods' => ['POST'],
                'uri' => '/orders',
                'name' => 'orders.store',
            ],
            [
                'action' => SampleOrderController::class.'@update',
                'methods' => ['PUT'],
                'uri' => '/orders/{id}',
                'name' => 'orders.update',
            ],
        ],
        CustomNamedRequest::class => [
            [
                'action' => SampleInvokableController::class,
                'methods' => ['POST'],
                'uri' => '/checkout',
                'name' => 'checkout',
            ],
        ],
    ]);
});
