<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | FormRequest Discovery Paths
    |--------------------------------------------------------------------------
    |
    | Directories scanned when discovering Laravel FormRequest classes.
    |
    */
    'paths' => [
        app_path('Http/Requests'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Directory
    |--------------------------------------------------------------------------
    |
    | Where generated TypeScript files are written (mirrors PHP namespaces).
    |
    */
    'output' => resource_path('js/formcast'),

    /*
    |--------------------------------------------------------------------------
    | Discovery Mode
    |--------------------------------------------------------------------------
    |
    | 'all' = every concrete FormRequest; 'attribute' = only #[Formcast] classes.
    |
    */
    'discovery' => 'all',

    /*
    |--------------------------------------------------------------------------
    | Message Resolution
    |--------------------------------------------------------------------------
    |
    | Set locales to [null] to use app()->getLocale(), or list specific
    | locales such as ['en', 'fil'].
    |
    */
    'messages' => [
        'enabled' => true,
        'locales' => [null],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Scanning
    |--------------------------------------------------------------------------
    |
    | Emit `usedBy` metadata by scanning the application route table.
    |
    */
    'routes' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Unsupported Rule Handling
    |--------------------------------------------------------------------------
    |
    | What to do with rules Formcast cannot map: 'server' | 'warn' | 'fail'.
    |
    */
    'unsupported' => 'server',

    /*
    |--------------------------------------------------------------------------
    | Server-Only Rules
    |--------------------------------------------------------------------------
    |
    | Extra rule names to always treat as server-only (Precognition / submit).
    |
    */
    'server_only' => [],

    /*
    |--------------------------------------------------------------------------
    | Redacted Fields
    |--------------------------------------------------------------------------
    |
    | Fields that must never appear in generated client code (e.g. sensitive
    | `in:` lists), keyed by FormRequest FQCN.
    |
    */
    'redact' => [
        // 'App\\Http\\Requests\\AdminRequest' => ['role'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Rule Mappers
    |--------------------------------------------------------------------------
    |
    | Map rule names to invokable classes implementing RuleMapper.
    |
    */
    'mappers' => [],

];
