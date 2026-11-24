# Laravel Wayfinder Integration

Formcast pairs naturally with [Laravel Wayfinder](https://github.com/laravel/wayfinder):

| Tool | Source of truth (PHP) | Generated for the client (TS) |
| :--- | :--- | :--- |
| **Wayfinder** | Routes & controllers | Typed route & action functions |
| **Formcast** | `FormRequest` rules, messages, attributes, enums | Typed Zod schemas, TS types, error messages, `usedBy` manifest |

## Submitting with Wayfinder Actions

`form.submit()` accepts a Wayfinder action object (`{ method, url }`) directly:

```ts
import { useFormcast } from '@rylacdang/formcast/vue'; // or '/react'
import StoreOrderRequest from '@/formcast/App/Http/Requests/StoreOrderRequest';
import { store } from '@/actions/App/Http/Controllers/OrderController';

const form = useFormcast(StoreOrderRequest, initialData);

const submit = () => form.submit(store());
```

## Precognition with Wayfinder

By default, `useFormcast()` auto-detects the Precognition endpoint from `cast.usedBy[0]` (populated by `RouteScanner`). When a `FormRequest` is shared across multiple routes, pass the Wayfinder action directly:

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
