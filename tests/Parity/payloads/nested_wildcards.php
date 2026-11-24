<?php

declare(strict_types=1);

return [
    'name' => 'NestedWildcards',
    'rules' => [
        'items' => ['required', 'array', 'min:1'],
        'items.*.product_id' => ['required', 'integer'],
        'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
    ],
    'messages' => [],
    'attributes' => [],
    'cases' => [
        [
            'label' => 'valid nested items pass',
            'payload' => [
                'items' => [
                    ['product_id' => 1, 'quantity' => 2],
                    ['product_id' => '42', 'quantity' => '5'],
                ],
            ],
        ],
        [
            'label' => 'empty array fails required',
            'payload' => [
                'items' => [],
            ],
        ],
        [
            'label' => 'invalid nested wildcard items fail with exact indexed keys and messages',
            'payload' => [
                'items' => [
                    ['product_id' => 1, 'quantity' => 150],
                    ['product_id' => '', 'quantity' => 0],
                ],
            ],
        ],
    ],
];
