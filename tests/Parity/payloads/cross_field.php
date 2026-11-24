<?php

declare(strict_types=1);

return [
    'name' => 'CrossFieldRules',
    'rules' => [
        'payment_method' => ['required', 'in:cash,gcash'],
        'gcash_reference' => ['nullable', 'required_if:payment_method,gcash', 'string', 'size:13'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
        'min_price' => ['required', 'integer', 'min:0'],
        'max_price' => ['required', 'integer', 'gt:min_price'],
        'start_date' => ['required', 'date'],
        'end_date' => ['required', 'date', 'after:start_date'],
    ],
    'messages' => [],
    'attributes' => [
        'gcash_reference' => 'GCash reference number',
    ],
    'cases' => [
        [
            'label' => 'cash order with valid confirmation and comparisons passes',
            'payload' => [
                'payment_method' => 'cash',
                'gcash_reference' => null,
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'min_price' => 10,
                'max_price' => 50,
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-10',
            ],
        ],
        [
            'label' => 'gcash order missing reference, mismatched confirmation, bad comparisons fail',
            'payload' => [
                'payment_method' => 'gcash',
                'gcash_reference' => '',
                'password' => 'secret123',
                'password_confirmation' => 'different',
                'min_price' => 50,
                'max_price' => 20,
                'start_date' => '2026-02-10',
                'end_date' => '2026-02-01',
            ],
        ],
        [
            'label' => 'gcash order with valid 13-char reference passes',
            'payload' => [
                'payment_method' => 'gcash',
                'gcash_reference' => '1234567890123',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'min_price' => 0,
                'max_price' => 1,
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-02',
            ],
        ],
    ],
];
