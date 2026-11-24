# Laravel Wayfinder Integration

Formcast pairs naturally with [Laravel Wayfinder](https://github.com/laravel/wayfinder):

| Tool | Source of truth (PHP) | Generated for the client (TS) |
| :--- | :--- | :--- |
| **Wayfinder** | Routes & controllers | Typed route & action functions (`@/actions/...`, `@/routes/...`) |
| **Formcast** | `FormRequest` rules, messages, attributes, enums | Typed Zod schemas, TS types, error messages, `usedBy` manifest, named-route exports (`@/formcast/routes/...`) |

## Submitting with Controller Actions or Named Routes

`form.submit()` accepts **both** Wayfinder `{ url, method }` objects (from controller actions or named routes) and Wayfinder `formVariants` `{ action, method }` objects (from `.form()`):

### Controller Actions

```ts
import { useFormcast } from '@rylacdang/formcast/vue'; // or '/react'
import StoreOrderRequest from '@/formcast/App/Http/Requests/StoreOrderRequest';
import { store } from '@/actions/App/Http/Controllers/OrderController';

const form = useFormcast(StoreOrderRequest, initialData);

const submit = () => form.submit(store());
```

### Importing Named Routes

When your routes are named (e.g. `->name('orders.store')`), Formcast also generates Wayfinder-style `routes/` re-exports under `resources/js/formcast/routes/`:

```ts
import { useFormcast } from '@rylacdang/formcast/vue'; // or '/react'
import { store as StoreOrderCast } from '@/formcast/routes/orders';
import { store } from '@/routes/orders';

const form = useFormcast(StoreOrderCast, initialData);

// Works with both store() ({ url, method }) and store.form() ({ action, method })
const submit = () => form.submit(store());
```

## Precognition with Wayfinder

By default, `useFormcast()` auto-detects the Precognition endpoint from `cast.usedBy[0]` (populated by `RouteScanner` for controller actions, invokable controllers, and closure routes). When a `FormRequest` is shared across multiple routes, pass the Wayfinder action or named route directly:

```ts
const form = useFormcast(StoreOrderRequest, initialData, {
  precognition: store(),
});
```

## Vite Setup Together

```ts
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import { formcast } from '@rylacdang/formcast/vite';

export default defineConfig({
  plugins: [
    laravel({ input: ['resources/js/app.ts'], refresh: true }),
    vue(),
    wayfinder({ formVariants: true }),
    formcast(),
  ],
});
```
