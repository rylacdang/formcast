# Vue + Inertia Patterns

## Standard Form with Wayfinder & Hybrid Precognition

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
    precognition: true, // auto-uses cast.usedBy[0] for serverFields after client passes
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

## Explicit Precognition Endpoint

When a `FormRequest` is used by multiple routes (or route scanning is disabled), pass the route explicitly:

```ts
const form = useFormcast(StoreOrderRequest, initialData, {
  precognition: store(),
});
```

## Wildcard Array Fields

Use dot-notation concrete paths matching Inertia's error keys:

```vue
<div v-for="(item, idx) in form.items" :key="idx">
  <input v-model="item.quantity" @blur="form.touch(`items.${idx}.quantity`)" />
  <p v-if="form.invalid(`items.${idx}.quantity`)">
    {{ form.errors[`items.${idx}.quantity`] }}
  </p>
</div>
```
