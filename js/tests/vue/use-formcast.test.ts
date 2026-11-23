import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { z } from 'zod';
import { defineCast, fc } from '../../src';

const mockSubmit = vi.fn();
const mockPrecognitionValidate = vi.fn();
const mockWithPrecognition = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
  useForm: (initial: Record<string, unknown>) => {
    const errors = reactive<Record<string, string | undefined>>({});
    const state = reactive({ ...initial });

    const form: Record<string, unknown> = {
      errors,
      get hasErrors() {
        return Object.values(errors).some((v) => typeof v === 'string' && v.length > 0);
      },
      processing: false,
      validating: false,
      data: () => ({ ...state }),
      setError(fieldOrMap: string | Record<string, string>, value?: string) {
        if (typeof fieldOrMap === 'string') {
          errors[fieldOrMap] = value;
        } else {
          Object.assign(errors, fieldOrMap);
        }
      },
      clearErrors(...fields: string[]) {
        if (fields.length === 0) {
          for (const key of Object.keys(errors)) {
            Reflect.deleteProperty(errors, key);
          }
          return;
        }
        for (const field of fields) {
          Reflect.deleteProperty(errors, field);
        }
      },
      submit: mockSubmit,
      validate: mockPrecognitionValidate,
    };

    const proxy: Record<string, unknown> = new Proxy(form, {
      get(target, prop, receiver): unknown {
        if (prop in state) {
          return Reflect.get(state, prop) as unknown;
        }
        return Reflect.get(target, prop, receiver) as unknown;
      },
      set(target, prop, value, receiver): boolean {
        if (prop in state) {
          return Reflect.set(state, prop, value);
        }
        return Reflect.set(target, prop, value, receiver);
      },
    });

    form['withPrecognition'] = (method: string, url: string) => {
      mockWithPrecognition(method, url);
      return proxy;
    };

    return proxy;
  },
}));

import { useFormcast } from '../../src/vue';

const StoreOrderCast = defineCast({
  name: 'StoreOrderRequest',
  schema: z
    .object({
      customer_email: fc.required(fc.email('Invalid email'), 'Email is required'),
      payment_method: fc.required(fc.in(['cash', 'gcash'], 'Invalid method'), 'Method required'),
      gcash_reference: fc.nullable(fc.string().length(13, 'Must be 13 chars')),
    })
    .superRefine(
      fc.requiredIf('gcash_reference', 'payment_method', ['gcash'], 'GCash ref required'),
    ),
  serverFields: ['customer_email'],
  dependencies: { payment_method: ['gcash_reference'] },
  usedBy: [{ action: 'OrderController@store', methods: ['POST'], uri: '/orders' }],
});

describe('useFormcast (Vue)', () => {
  beforeEach(() => {
    mockSubmit.mockReset();
    mockPrecognitionValidate.mockReset();
    mockWithPrecognition.mockReset();
  });

  it('validates fields on touch, skips Precognition when client fails, and calls Precognition after client passes', () => {
    const form = useFormcast(StoreOrderCast, {
      customer_email: 'not-valid',
      payment_method: 'cash',
      gcash_reference: null as string | null,
    });

    expect(mockWithPrecognition).toHaveBeenCalledWith('post', '/orders');

    // Client rule fails -> error set, Precognition NOT called
    form.touch('customer_email');
    expect(form.invalid('customer_email')).toBe(true);
    expect(form.valid('customer_email')).toBe(false);
    expect(form.errors['customer_email']).toBe('Invalid email');
    expect(mockPrecognitionValidate).not.toHaveBeenCalled();

    // Fix email -> client passes -> error cleared AND Precognition called
    form.customer_email = 'buyer@example.com';
    form.touch('customer_email');
    expect(form.invalid('customer_email')).toBe(false);
    expect(form.valid('customer_email')).toBe(true);
    expect(mockPrecognitionValidate).toHaveBeenCalledWith('customer_email');
  });

  it('revalidates dependent fields and blocks submit when invalid, then forwards Wayfinder route when valid', () => {
    const form = useFormcast(StoreOrderCast, {
      customer_email: 'buyer@example.com',
      payment_method: 'gcash',
      gcash_reference: '',
    });

    // Submit blocks while gcash_reference is missing
    const blocked = form.submit({ method: 'post', url: '/orders' });
    expect(blocked).toBe(false);
    expect(form.errors['gcash_reference']).toBe('GCash ref required');
    expect(mockSubmit).not.toHaveBeenCalled();

    // Fix gcash_reference and submit with Wayfinder route object
    form.gcash_reference = '1234567890123';
    const submitted = form.submit({ method: 'post', url: '/orders' });
    expect(submitted).toBe(true);
    expect(mockSubmit).toHaveBeenCalledWith('post', '/orders', undefined);
  });
});
