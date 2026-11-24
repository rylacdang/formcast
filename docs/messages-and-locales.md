# Messages & Locales

Formcast resolves validation error messages using Laravel's own `Translator` so client-side errors match Laravel's 422 responses word for word.

## Resolution Precedence

For each field and rule, `MessageResolver` checks in Laravel's exact order:

1. `FormRequest::messages()` — exact `field.rule`, then wildcard match (e.g. `items.*.quantity.max`), then rule-only key.
2. `lang/{locale}/validation.php` `custom` array (`validation.custom.{field}.{rule}`).
3. Default `lang/{locale}/validation.php` lines, selecting type-specific size variants (`min.string`, `min.numeric`, `min.array`, `min.file`) based on Laravel's `getSize()` precedence.

## Attribute Names

Attribute labels resolve in order:

1. `FormRequest::attributes()` (exact or wildcard key)
2. `lang/{locale}/validation.php` `attributes` array
3. Laravel's fallback (snake-to-words for top-level attributes like `customer_email` → `customer email`; dotted nested attributes like `items.*.product_id` preserve underscores)

## Placeholders

- Static placeholders (`:attribute`, `:min`, `:max`, `:size`, `:values`, `:other`, `:date`, `:format`) are substituted at generation time.
- Runtime placeholders (`:index`, `:position`, wildcard `items.*.quantity`, and comparison `:value` for `gt`/`gte`/`lt`/`lte`) are resolved at validation time in the browser.

## Generating for a Specific Locale

```bash
php artisan formcast:generate --locale=fil
```

Or configure the default locale in `config/formcast.php`:

```php
'messages' => [
    'enabled' => true,
    'locales' => ['fil'],
],
```
