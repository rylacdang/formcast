# Vue + Inertia Guide

Import `useFormcast` from `@rylacdang/formcast/vue`:

```vue
<script setup lang="ts">
import { useFormcast } from '@rylacdang/formcast/vue';
import StoreOrderRequest from '@/formcast/App/Http/Requests/StoreOrderRequest';
import { store } from '@/actions/App/Http/Controllers/OrderController';

const form = useFormcast(
  StoreOrderRequest,
  {
    customer_email: '',
    payment_method: 'cash',
    gcash_reference: null as string | null,
    items: [{ product_id: 1, quantity: 1 }],
    notes: null as string | null,
  },
  {
    validateOn: 'blur', // 'blur' | 'input' | 'submit'
    precognition: true, // only for serverFields after client checks pass
  },
);

const submit = () => form.submit(store());
</script>

<template>
  <form @submit.prevent="submit">
    <input v-model="form.customer_email" @blur="form.touch('customer_email')" />
    <p v-if="form.invalid('customer_email')">{{ form.errors.customer_email }}</p>
    <span v-if="form.validating">Checking…</span>

    <button :disabled="form.processing || form.hasErrors">Place order</button>
  </form>
</template>
```

## Returned Helpers

`useFormcast` returns the full Inertia `useForm` object plus:

- `form.touch(field)`: marks `field` as touched and runs field-level validation (unless `validateOn === 'submit'`).
- `form.touched(field?)`: returns whether `field` (or any field) has been touched.
- `form.validate(field?)`: validates one field (and its dependents) or all fields, returning a boolean.
- `form.invalid(field)`: returns `true` when `form.errors[field]` is set.
- `form.valid(field)`: returns `true` when `field` is touched and has no error.
- `form.validating`: reflects Inertia Precognition's validating state.
- `form.submit(routeOrMethod, url?, options?)`: runs `validate()` first; stops if invalid, otherwise submits via Inertia.

> [!CAUTION]
> Client-side validation is for user experience only. **Laravel validation on the server is never optional.**
