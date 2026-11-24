<?php

declare(strict_types=1);

return [
    'name' => 'FormatsAndSizes',
    'rules' => [
        'email' => ['required', 'email', 'max:100'],
        'slug' => ['required', 'string', 'alpha_dash', 'min:3', 'max:30'],
        'status' => ['required', 'in:draft,published,archived'],
        'code' => ['required', 'string', 'size:6', 'starts_with:FC-'],
    ],
    'messages' => [],
    'attributes' => [],
    'cases' => [
        [
            'label' => 'all formats and sizes pass',
            'payload' => [
                'email' => 'hello@example.com',
                'slug' => 'my_order-01',
                'status' => 'published',
                'code' => 'FC-123',
            ],
        ],
        [
            'label' => 'invalid email, bad slug, unknown status, and wrong code size fail',
            'payload' => [
                'email' => 'not-an-email',
                'slug' => 'bad slug!',
                'status' => 'deleted',
                'code' => 'FC-12',
            ],
        ],
        [
            'label' => 'wrong prefix fails starts_with',
            'payload' => [
                'email' => 'ok@example.com',
                'slug' => 'valid-slug',
                'status' => 'draft',
                'code' => 'XX-123',
            ],
        ],
    ],
];
