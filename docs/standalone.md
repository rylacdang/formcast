# Standalone Usage (Expo / React Native / Axios)

The core `@rylacdang/formcast` entry point is completely **framework-agnostic**—it has zero imports from `vue`, `react`, `vite`, or `@inertiajs/*`, so you can use your generated `FormRequest` schemas anywhere JavaScript runs: **Expo / React Native**, plain **Axios / `fetch`** clients, or Node scripts.

> [!CAUTION]
> Client-side validation is for user experience only. **Laravel validation on the server is never optional.**

## Installation

Install the core package and `zod` in your client app:

```bash
npm install @rylacdang/formcast zod
```

Copy or share the generated files from `resources/js/formcast/` (for example via a monorepo workspace package or a build step).

## Basic Validation (`createValidator`)

```ts
import { createValidator } from '@rylacdang/formcast';
import StoreOrderRequest from './formcast/App/Http/Requests/StoreOrderRequest';

const validator = createValidator(StoreOrderRequest);

const result = validator.validateAll({
  customer_email: 'not-an-email',
  payment_method: 'gcash',
  gcash_reference: '',
  items: [{ product_id: 1, quantity: 150 }],
  notes: null,
});

if (!result.valid) {
  console.log(result.errors);
  // {
  //   customer_email: 'The customer email field must be a valid email address.',
  //   gcash_reference: 'The GCash reference number field is required when payment method is gcash.',
  //   'items.0.quantity': 'The items.0.quantity field must not be greater than 99.',
  // }
}
```

## Field-Level Validation

Use `validateField(path, data)` to validate a single field and automatically revalidate any dependent fields (like `payment_method` → `gcash_reference`):

```ts
const fieldResult = validator.validateField('customer_email', formData);

// fieldResult.valid       -> boolean
// fieldResult.errors      -> Record<string, string> (only affected fields)
// fieldResult.cleared     -> string[] (fields whose client errors can be cleared)
// fieldResult.needsServer -> true if client rules passed AND field has server-only rules
```

## Expo / React Native Example

```tsx
import { useState } from 'react';
import { Button, Text, TextInput, View } from 'react-native';
import { createValidator, type ErrorBag } from '@rylacdang/formcast';
import StoreOrderRequest, {
  type StoreOrderRequestInput,
} from '../shared/formcast/App/Http/Requests/StoreOrderRequest';

const validator = createValidator(StoreOrderRequest);

export function CreateOrderScreen() {
  const [data, setData] = useState<StoreOrderRequestInput>({
    customer_email: '',
    payment_method: 'cash',
    gcash_reference: null,
    items: [{ product_id: 1, quantity: 1 }],
    notes: null,
  });
  const [errors, setErrors] = useState<ErrorBag>({});

  const onBlurField = (field: string) => {
    const result = validator.validateField(field, data);
    setErrors((prev) => {
      const next = { ...prev };
      for (const cleared of result.cleared) {
        delete next[cleared];
      }
      return { ...next, ...result.errors };
    });
  };

  const onSubmit = async () => {
    const result = validator.validateAll(data);
    if (!result.valid) {
      setErrors(result.errors);
      return;
    }

    setErrors({});
    const response = await fetch('https://api.example.com/orders', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(data),
    });

    if (response.status === 422) {
      const body = (await response.json()) as { errors?: Record<string, string[]> };
      const serverErrors: ErrorBag = {};
      for (const [key, messages] of Object.entries(body.errors ?? {})) {
        if (messages[0]) serverErrors[key] = messages[0];
      }
      setErrors(serverErrors);
    }
  };

  return (
    <View>
      <TextInput
        value={data.customer_email}
        onChangeText={(value) => setData((prev) => ({ ...prev, customer_email: value }))}
        onBlur={() => onBlurField('customer_email')}
        placeholder="Email"
      />
      {errors.customer_email && <Text>{errors.customer_email}</Text>}
      <Button title="Place Order" onPress={() => void onSubmit()} />
    </View>
  );
}
```

## Axios Example

```ts
import axios, { isAxiosError } from 'axios';
import { createValidator, type ErrorBag } from '@rylacdang/formcast';
import StoreOrderRequest, {
  type StoreOrderRequestInput,
} from '@/formcast/App/Http/Requests/StoreOrderRequest';

const validator = createValidator(StoreOrderRequest);

export async function submitOrder(payload: StoreOrderRequestInput): Promise<ErrorBag> {
  const check = validator.validateAll(payload);
  if (!check.valid) {
    return check.errors;
  }

  try {
    await axios.post('/api/orders', payload);
    return {};
  } catch (error) {
    if (isAxiosError<{ errors?: Record<string, string[]> }>(error) && error.response?.status === 422) {
      const bag: ErrorBag = {};
      for (const [field, messages] of Object.entries(error.response.data.errors ?? {})) {
        if (messages[0]) bag[field] = messages[0];
      }
      return bag;
    }
    throw error;
  }
}
```
