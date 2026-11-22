<?php

declare(strict_types=1);

use Rylacdang\Formcast\Emitters\ManifestEmitter;
use Rylacdang\Formcast\Emitters\TypeEmitter;

it('emits input and output type aliases for a cast', function (): void {
    $emitter = new TypeEmitter;

    expect($emitter->emit('StoreOrderRequest'))->toBe(<<<'TS'
export type StoreOrderRequestInput = z.input<typeof schema>;
export type StoreOrderRequestOutput = z.output<typeof schema>;
TS);
});

it('emits deterministic defineCast manifest with sorted serverFields, dependencies, and usedBy', function (): void {
    $emitter = new ManifestEmitter;

    $output = $emitter->emit(
        exportName: 'StoreOrderRequest',
        serverFields: ['items.*.product_id', 'customer_email', 'customer_email'],
        dependencies: [
            'payment_method' => ['gcash_reference'],
            'items.*.start_date' => ['items.*.end_date'],
        ],
        usedBy: [
            [
                'action' => 'App\\Http\\Controllers\\OrderController@store',
                'methods' => ['POST'],
                'uri' => '/orders',
                'name' => 'orders.store',
            ],
        ],
    );

    expect($output)->toBe(<<<'TS'
export const StoreOrderRequest = defineCast({
  name: 'StoreOrderRequest',
  schema,
  serverFields: ['customer_email', 'items.*.product_id'],
  dependencies: {
    'items.*.start_date': ['items.*.end_date'],
    payment_method: ['gcash_reference'],
  },
  usedBy: [
    { action: 'App\\Http\\Controllers\\OrderController@store', methods: ['POST'], uri: '/orders', name: 'orders.store' },
  ],
});
TS);
});

it('emits compact empty collections when serverFields, dependencies, and usedBy are empty', function (): void {
    $emitter = new ManifestEmitter;

    expect($emitter->emit('SimpleRequest'))->toBe(<<<'TS'
export const SimpleRequest = defineCast({
  name: 'SimpleRequest',
  schema,
  serverFields: [],
  dependencies: {},
  usedBy: [],
});
TS);
});
