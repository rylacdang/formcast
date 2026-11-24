---
name: formcast
description: Use when creating or editing Laravel FormRequests, Inertia forms (Vue or React), or client-side validation in an app that uses Formcast (rylacdang/formcast). Covers generating Zod schemas, using useFormcast, hybrid Precognition validation, custom client rules, and troubleshooting generated output.
---

# Formcast

Formcast casts Laravel `FormRequest` classes into typed Zod schemas, TypeScript input/output types, Laravel-identical error messages, and a server-rule manifest for hybrid validation.

## Golden Rules

1. **Single source of truth:** Validation rules live ONLY in PHP `FormRequest` classes. Change rules in PHP, never in generated TypeScript.
2. **Never edit generated files:** Treat `resources/js/formcast/**` as read-only build output; regenerate with `php artisan formcast:generate`.
3. **Use `useFormcast()`:** For any Inertia form backed by a `FormRequest`, use `useFormcast()` from `@rylacdang/formcast/vue` or `@rylacdang/formcast/react` instead of raw `useForm()`.
4. **Do not duplicate server-only rules:** Rules like `unique`, `exists`, `current_password`, closures, and opaque `ValidationRule` objects automatically go into `serverFields` and are validated via Inertia Precognition after client rules pass.
5. **Server validation is never optional:** Client-side validation is UX-only. Laravel always re-validates every request on submit.

## Workflow: Adding a New Validated Form

1. Create the request: `php artisan make:request StoreThingRequest`.
2. Define `rules()` (plus `messages()` / `attributes()` if needed).
3. Type-hint the `FormRequest` in the controller action so `RouteScanner` populates `usedBy`.
4. Run `php artisan formcast:generate` (or let the `formcast()` Vite plugin regenerate automatically in dev).
5. Import the generated cast:
   ```ts
   import StoreThingRequest from '@/formcast/App/Http/Requests/StoreThingRequest';
   ```
6. Bind the form with `useFormcast(StoreThingRequest, initialData)` and submit with a Wayfinder action (`form.submit(store())`) or method + URL (`form.submit('post', '/things')`).

## Request-Dependent Rules

If `rules()` relies on runtime state like `$this->user()` or `$this->route('order')` and throws during generation, define a `formcastRules(): array` method returning the client-relevant subset without request dependencies.

## Custom Client-Side Rules

- Implement `Rylacdang\Formcast\Contracts\CastsToZod` on a custom `ValidationRule` class, OR
- Register a mapper in a service provider via `Formcast::extend('rule_name', fn (ZodContext $ctx) => ...)`.
- Any custom rule without a mapper is safely treated as server-only (`serverFields`).

## Commands

| Command | Purpose |
| :--- | :--- |
| `php artisan formcast:generate` | Generate all casts, enums, barrel index, and `.formcast.json` |
| `php artisan formcast:generate --path=app/Http/Requests/Billing` | Limit generation to specific path(s) |
| `php artisan formcast:generate --locale=fil` | Resolve messages for a specific locale |
| `php artisan formcast:generate --dry-run` | Preview changes without modifying files |
| `php artisan formcast:generate --check` | Exit non-zero if generated files are stale (for CI) |

## References

- `references/rule-support.md` — full client vs server rule support matrix
- `references/vue-patterns.md` — Vue 3 + Inertia + Wayfinder patterns
- `references/react-patterns.md` — React + Inertia + Wayfinder patterns
- `references/troubleshooting.md` — common generation and runtime issues
