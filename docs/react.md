# React + Inertia Guide

Import `useFormcast` from `@rylacdang/formcast/react`:

```tsx
import { useFormcast } from '@rylacdang/formcast/react';
import StoreOrderRequest from '@/formcast/App/Http/Requests/StoreOrderRequest';
import { store } from '@/actions/App/Http/Controllers/OrderController';

export default function CreateOrder() {
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
      validateOn: 'blur',
      precognition: true,
    },
  );

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        form.submit(store());
      }}
    >
      <input
        value={form.data.customer_email}
        onChange={(e) => form.setData('customer_email', e.target.value)}
        onBlur={() => form.touch('customer_email')}
      />
      {form.invalid('customer_email') && <p>{form.errors.customer_email}</p>}
      {form.validating && <span>Checking…</span>}

      <button disabled={form.processing || form.hasErrors}>Place order</button>
    </form>
  );
}
```

## API Parity with Vue

`useFormcast` for React exposes the exact same helpers as the Vue composable (`touch`, `touched`, `validate`, `invalid`, `valid`, `validating`, and Wayfinder-compatible `submit`), while preserving `@inertiajs/react`'s `form.data` and `form.setData(...)` interface.

> [!CAUTION]
> Client-side validation is for user experience only. **Laravel validation on the server is never optional.**
