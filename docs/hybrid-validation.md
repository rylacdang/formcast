# Hybrid Validation & Precognition

Formcast splits validation into two fast, complementary layers:

1. **Client-safe rules (`0 ms`, zero HTTP requests):** `required`, `email`, `min`, `max`, `in`, `enum`, `required_if`, `confirmed`, `gt`, `after`, etc. run instantly in the browser using the generated Zod schema.
2. **Server-only rules (`serverFields`):** `unique`, `exists`, `current_password`, `email:dns`, unsafe regexes, closures, and opaque custom `ValidationRule` classes are listed in `cast.serverFields`.

## How `touch(field)` Works

1. Formcast validates `field` (and any dependent fields in `cast.dependencies`) against the generated Zod schema.
2. **If client validation fails:** the Laravel-identical error is shown immediately via `form.setError()`, and **no HTTP request is sent**.
3. **If client validation passes AND `field` is in `serverFields`:** Formcast calls Inertia's built-in Precognition (`form.validate(field)`, available in Inertia `>=2.3`) to check the server-only rule.
4. **On `form.submit(...)`:** Formcast runs `validateAll()` first; if valid, submits to Laravel, which always validates the complete request as the final authority.

## Configuring Precognition

```ts
// Auto-detects endpoint from cast.usedBy[0] (default)
const form = useFormcast(StoreOrderRequest, initialData, { precognition: true });

// Explicit endpoint (Wayfinder action or { method, url })
const form = useFormcast(StoreOrderRequest, initialData, { precognition: store() });

// Client-side only (server rules checked on submit)
const form = useFormcast(StoreOrderRequest, initialData, { precognition: false });
```

On Inertia `<2.3`, Formcast feature-detects Precognition and gracefully falls back to submit-only server checks.
