# Troubleshooting

## A `FormRequest` is skipped with a warning

If `rules()` throws during generation (typically because it accesses `$this->user()` or `$this->route('model')` without null checks), Formcast skips that request and prints a warning instead of aborting.

**Fix:** Guard with null-safe operators (`$this->user()?->id`) or define a `formcastRules(): array` method returning the client-safe subset of rules.

## Notice about `prepareForValidation()`

Formcast prints a notice when a `FormRequest` overrides `prepareForValidation()` because server-side input transformations cannot be automatically replicated in the browser. Ensure your UI sends normalized values or mirrors the same transformation client-side.

## A field is only validated on submit

Check `serverFields` in the generated `.ts` file. Fields with `unique`, `exists`, `current_password`, `email:dns`, unsafe PCRE regexes, closures, or unmapped custom rules are intentionally routed to the server.

- For live feedback on server-only fields, make sure Inertia `>=2.3` is installed and `precognition: true` is enabled on `useFormcast()`.
- For custom rules that can run in the browser, see [Custom Client-Side Rules](custom-rules.md).

## `php artisan formcast:generate --check` fails in CI

Your generated files under `resources/js/formcast/` are out of date with your PHP `FormRequest` classes. Run `php artisan formcast:generate` locally and commit the updated output (or gitignore the output directory and generate during your build step).

## Avoiding exposing internal values to the browser

Generated TypeScript schemas are bundled and shipped to the client. Exclude sensitive fields (like internal admin role `in:` lists) using `redact` in `config/formcast.php`:

```php
'redact' => [
    App\Http\Requests\AdminRequest::class => ['role'],
],
```
