## Formcast

This application uses Formcast (`rylacdang/formcast`) to generate typed Zod schemas, TypeScript types, and Laravel-identical error messages from Laravel `FormRequest` classes.

- Validation rules live ONLY in `app/Http/Requests/*Request.php`. Never hand-write duplicate client-side validation for a form backed by a `FormRequest`.
- Generated files live in `{{ config('formcast.output', 'resources/js/formcast') }}` and must never be edited by hand.
- After changing rules, messages, attributes, or backed enums, run `php artisan formcast:generate` (the Vite plugin does this automatically in dev).
- In Inertia pages, use `useFormcast()` from `@rylacdang/formcast/vue` or `@rylacdang/formcast/react` instead of raw `useForm()` for forms backed by a `FormRequest`.
- Client-side validation is for user experience only. Laravel still validates every request on the server—never remove or weaken server validation.
- Activate the `formcast` skill for detailed workflows, framework patterns, custom rules, and troubleshooting.
