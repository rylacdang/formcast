<p align="center">
  <img src="art/logo.svg" width="380" alt="Formcast">
</p>

<p align="center">
  <strong>Cast your Laravel <code>FormRequest</code> rules to the client.</strong>
</p>

<p align="center">
  <a href="https://github.com/rylacdang/formcast/actions/workflows/php-tests.yml"><img src="https://github.com/rylacdang/formcast/actions/workflows/php-tests.yml/badge.svg" alt="PHP Tests"></a>
  <a href="https://github.com/rylacdang/formcast/actions/workflows/js-tests.yml"><img src="https://github.com/rylacdang/formcast/actions/workflows/js-tests.yml/badge.svg" alt="JS Tests"></a>
  <a href="https://github.com/rylacdang/formcast/actions/workflows/parity.yml"><img src="https://github.com/rylacdang/formcast/actions/workflows/parity.yml/badge.svg" alt="Parity"></a>
  <a href="https://github.com/rylacdang/formcast/actions/workflows/static-analysis.yml"><img src="https://github.com/rylacdang/formcast/actions/workflows/static-analysis.yml/badge.svg" alt="Static Analysis"></a>
  <a href="LICENSE.md"><img src="https://img.shields.io/badge/License-MIT-blue.svg" alt="License: MIT"></a>
</p>

Formcast reads your Laravel `FormRequest` classes and generates typed **Zod schemas**, **TypeScript types**, and **Laravel-identical error messages** for your Inertia (Vue / React) and standalone (Expo / Axios) apps. Client-safe rules validate instantly in the browser (`0 ms`), while server-only rules (`unique`, `exists`, closures) are routed through Inertia's built-in Precognition only after client checks pass.

> [!CAUTION]
> Client-side validation is for user experience only. **Laravel validation on the server is never optional.**

## Installation

```bash
composer require rylacdang/formcast
npm install -D @rylacdang/formcast zod
php artisan formcast:install
```

Add the Vite plugin to `vite.config.ts`:

```ts
import { formcast } from '@rylacdang/formcast/vite';

export default defineConfig({
  plugins: [
    laravel({ input: ['resources/js/app.ts'], refresh: true }),
    vue(), // or react()
    wayfinder({ formVariants: true }),
    formcast(),
  ],
});
```

## 60-Second Example

### 1. Write your normal Laravel `FormRequest`

```php
class StoreOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_email'     => ['required', 'email', 'max:255', 'unique:orders,customer_email'],
            'payment_method'     => ['required', Rule::enum(PaymentMethod::class)],
            'gcash_reference'    => ['nullable', 'required_if:payment_method,gcash', 'string', 'size:13'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1', 'max:99'],
            'notes'              => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['gcash_reference' => 'GCash reference number'];
    }
}
```

### 2. Generate typed casts (or let the Vite plugin do it automatically)

```bash
php artisan formcast:generate
```

### 3. Use in Vue or React with Inertia + Wayfinder

```vue
<script setup lang="ts">
import { useFormcast } from '@rylacdang/formcast/vue';
import StoreOrderRequest from '@/formcast/App/Http/Requests/StoreOrderRequest';
import { store } from '@/actions/App/Http/Controllers/OrderController';

const form = useFormcast(StoreOrderRequest, {
  customer_email: '',
  payment_method: 'cash',
  gcash_reference: null,
  items: [{ product_id: 1, quantity: 1 }],
  notes: null,
});
</script>

<template>
  <form @submit.prevent="form.submit(store())">
    <input v-model="form.customer_email" @blur="form.touch('customer_email')" />
    <p v-if="form.invalid('customer_email')">{{ form.errors.customer_email }}</p>
    <span v-if="form.validating">Checking…</span>
    <button :disabled="form.processing || form.hasErrors">Place order</button>
  </form>
</template>
```

## Why Formcast?

| Feature | Other Zod generators | Precognition only | **Formcast** |
| :--- | :---: | :---: | :---: |
| `FormRequest` → Zod + TS types | ✅ | ❌ | ✅ |
| **Laravel-identical messages** (`messages()`, `attributes()`, `lang/`) | ❌ / partial | ✅ | ✅ |
| **Hybrid validation** (Zod first, Precognition only for server rules) | ❌ | ❌ (every check hits server) | ✅ |
| **First-class Inertia `useFormcast()`** (Vue & React) | ❌ | ✅ | ✅ |
| **Vite plugin** with debounced auto-regeneration | ❌ | N/A | ✅ |
| **Controller/route mapping** (`usedBy`, Wayfinder-ready) | ❌ | N/A | ✅ |
| **Laravel semantics** (`TrimStrings`, `""` = missing, `"5"` int) | partial | ✅ | ✅ |
| **Automated Laravel-vs-Zod parity suite** | ❌ | N/A | ✅ |
| **Framework-agnostic core** (Expo / React Native / Axios) | ❌ | ❌ | ✅ |
| **Ships Laravel Boost guidelines & AI skill** | ❌ | ❌ | ✅ |

## Documentation

- [Installation & Configuration](docs/installation.md)
- [Generating Casts & CI Checks](docs/generating.md)
- [Vue + Inertia Guide](docs/vue.md)
- [React + Inertia Guide](docs/react.md)
- [Standalone Usage (Expo / React Native / Axios)](docs/standalone.md)
- [Hybrid Validation & Precognition](docs/hybrid-validation.md)
- [Messages & Locales](docs/messages-and-locales.md)
- [Laravel Wayfinder Integration](docs/wayfinder.md)
- [Rule Support Matrix](docs/rule-support.md)
- [Custom Client-Side Rules](docs/custom-rules.md)
- [AI Skill & Laravel Boost](docs/ai-skill.md)
- [Troubleshooting](docs/troubleshooting.md)

## License

Formcast is open-sourced software licensed under the [MIT license](LICENSE.md).
