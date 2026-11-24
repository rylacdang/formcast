# Generating Casts & CI Checks

## Basic Command

```bash
php artisan formcast:generate
```

Formcast discovers your `FormRequest` classes, extracts rules/messages/attributes, builds nested field trees, emits Zod schemas, shared backed enums, TypeScript types, `defineCast()` manifests, a barrel `index.ts`, and a `.formcast.json` content-hash manifest.

Only files whose content hash changed are rewritten, and files for deleted `FormRequest` classes are automatically removed.

## Options

| Option | Description |
| :--- | :--- |
| `--path=...` | Limit generation to one or more paths (repeatable) |
| `--locale=fil` | Resolve validation messages for a specific locale |
| `--dry-run` | Print summary counts without modifying disk |
| `--check` | Exit with code `1` if generated files are stale (for CI) |

## Controlling Discovery with Attributes

```php
use Rylacdang\Formcast\Attributes\Formcast;
use Rylacdang\Formcast\Attributes\FormcastIgnore;

#[Formcast(name: 'StoreOrder')] // custom export name (required when discovery = 'attribute')
class StoreOrderRequest extends FormRequest { /* ... */ }

#[FormcastIgnore] // never generate this request
class InternalWebhookRequest extends FormRequest { /* ... */ }
```

## Request-Dependent `rules()`

Formcast instantiates each `FormRequest` inside a `GenerationContext` (null-safe user and bound dummy route). If a request still throws during generation, Formcast skips it with a warning.

To provide client-safe rules without request dependencies, define a `formcastRules()` method:

```php
public function formcastRules(): array
{
    return [
        'email' => ['required', 'email', 'max:255'],
    ];
}
```

## CI Drift Check

Add `--check` to your CI workflow so PRs fail if a `FormRequest` changed without regenerating:

```bash
php artisan formcast:generate --check
```
