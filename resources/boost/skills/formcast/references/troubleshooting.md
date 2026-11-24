# Troubleshooting Formcast

## "Skipped App\Http\Requests\..." warning during `formcast:generate`

`rules()` threw an exception during generation (commonly because it accesses `$this->user()->id` or `$this->route('model')->id` without null checks).

**Fix:** Either guard with null-safe operators (`$this->user()?->id`) or add a `formcastRules(): array` method to the `FormRequest` returning the client-safe rules.

## Field is only validated on submit (not instantly on blur)

Check `serverFields` in the generated `.ts` file. Formcast marks a field as server-only when it uses `unique`, `exists`, `current_password`, `email:dns`, an unsafe PCRE regex, a closure, or an unknown custom rule.

- If it's a custom rule you want on the client, implement `CastsToZod` or register `Formcast::extend(...)`.
- If you want live feedback for server-only fields, make sure Inertia `>=2.3` is installed and `precognition: true` is enabled on `useFormcast()`.

## Notice about `prepareForValidation()`

Formcast prints a notice when a `FormRequest` overrides `prepareForValidation()` because server-side input mutations (e.g. normalizing slugs or stripping formatting) cannot be automatically replicated on the client. Keep client inputs clean or perform the same normalization in the UI.

## CI fails on `php artisan formcast:generate --check`

Generated files on disk do not match the current PHP `FormRequest` classes. Run `php artisan formcast:generate` locally and commit the updated files (or gitignore `resources/js/formcast` and run generation during your CI build step).

## Sensitive values in generated files

Generated TypeScript files are bundled into the browser. Never expose secret lists or internal roles via client-emitted `in:` rules—add those fields to `redact` in `config/formcast.php`.
