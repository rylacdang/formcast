<?php

declare(strict_types=1);

return [
    'name' => 'PresenceAndTypes',
    'rules' => [
        'name' => ['required', 'string', 'max:20'],
        'count' => ['required', 'integer', 'min:1', 'max:99'],
        'price' => ['nullable', 'numeric', 'min:0.5'],
        'active' => ['required', 'boolean'],
        'nickname' => ['sometimes', 'string', 'min:3'],
    ],
    'messages' => [],
    'attributes' => [],
    'cases' => [
        [
            'label' => 'valid payload with string integer and numeric boolean',
            'payload' => [
                'name' => '  Ryan  ',
                'count' => '5',
                'price' => '12.50',
                'active' => '1',
            ],
        ],
        [
            'label' => 'valid payload with null nullable and boolean 0',
            'payload' => [
                'name' => 'Formcast',
                'count' => 10,
                'price' => null,
                'active' => 0,
                'nickname' => 'dev',
            ],
        ],
        [
            'label' => 'empty string, whitespace-only, and float string for integer fail',
            'payload' => [
                'name' => '   ',
                'count' => '5.0',
                'price' => '',
                'active' => 'yes',
            ],
        ],
        [
            'label' => 'missing required fields fail',
            'payload' => [
                'count' => '',
                'active' => false,
                'nickname' => 'ab',
            ],
        ],
    ],
];
