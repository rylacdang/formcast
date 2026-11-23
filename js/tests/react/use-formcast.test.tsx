// @vitest-environment happy-dom
import { act, renderHook } from '@testing-library/react';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { z } from 'zod';
import { defineCast, fc } from '../../src';

const mockSubmit = vi.fn();
const mockPrecognitionValidate = vi.fn();
const mockWithPrecognition = vi.fn();

vi.mock('@inertiajs/react', () => ({
  useForm: <T extends Record<string, unknown>>(initial: T) => {
    const [data, setDataState] = useState<T>(initial);
    const [errors, setErrorsState] = useState<Record<string, string | undefined>>({});

    const setData = (keyOrData: string | T | ((prev: T) => T), value?: unknown): void => {
      if (typeof keyOrData === 'function') {
        setDataState(keyOrData);
      } else if (typeof keyOrData === 'string') {
        setDataState((prev) => ({ ...prev, [keyOrData]: value }));
      } else {
        setDataState(keyOrData);
      }
    };

    const setError = (fieldOrMap: string | Record<string, string>, value?: string): void => {
      setErrorsState((prev) =>
        typeof fieldOrMap === 'string'
          ? { ...prev, [fieldOrMap]: value }
          : { ...prev, ...fieldOrMap },
      );
    };

    const clearErrors = (...fields: string[]): void => {
      if (fields.length === 0) {
        setErrorsState({});
        return;
      }
      setErrorsState((prev) => {
        const next = { ...prev };
        for (const field of fields) {
          Reflect.deleteProperty(next, field);
        }
        return next;
      });
    };

    const form = {
      data,
      errors,
      hasErrors: Object.values(errors).some((v) => typeof v === 'string' && v.length > 0),
      processing: false,
      validating: false,
      setData,
      setError,
      clearErrors,
      submit: mockSubmit,
      validate: mockPrecognitionValidate,
      withPrecognition(method: string, url: string) {
        mockWithPrecognition(method, url);
        return form;
      },
    };

    return form;
  },
}));

import { useFormcast } from '../../src/react';

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

describe('useFormcast (React)', () => {
  beforeEach(() => {
    mockSubmit.mockReset();
    mockPrecognitionValidate.mockReset();
    mockWithPrecognition.mockReset();
  });

  it('validates on touch, skips Precognition on client failure, and triggers Precognition after client passes', () => {
    const { result } = renderHook(() =>
      useFormcast(StoreOrderCast, {
        customer_email: 'bad-email',
        payment_method: 'cash',
        gcash_reference: null as string | null,
      }),
    );

    expect(mockWithPrecognition).toHaveBeenCalledWith('post', '/orders');

    act(() => {
      result.current.touch('customer_email');
    });

    expect(result.current.invalid('customer_email')).toBe(true);
    expect(result.current.valid('customer_email')).toBe(false);
    expect(result.current.errors['customer_email']).toBe('Invalid email');
    expect(mockPrecognitionValidate).not.toHaveBeenCalled();

    act(() => {
      result.current.setData('customer_email', 'buyer@example.com');
    });

    act(() => {
      result.current.touch('customer_email');
    });

    expect(result.current.invalid('customer_email')).toBe(false);
    expect(result.current.valid('customer_email')).toBe(true);
    expect(mockPrecognitionValidate).toHaveBeenCalledWith('customer_email');
  });

  it('blocks submit when invalid and forwards Wayfinder route when valid', () => {
    const { result } = renderHook(() =>
      useFormcast(StoreOrderCast, {
        customer_email: 'buyer@example.com',
        payment_method: 'gcash',
        gcash_reference: '',
      }),
    );

    let submitted = true;
    act(() => {
      submitted = result.current.submit({ method: 'post', url: '/orders' });
    });

    expect(submitted).toBe(false);
    expect(result.current.errors['gcash_reference']).toBe('GCash ref required');
    expect(mockSubmit).not.toHaveBeenCalled();

    act(() => {
      result.current.setData('gcash_reference', '1234567890123');
    });

    act(() => {
      submitted = result.current.submit({ method: 'post', url: '/orders' });
    });

    expect(submitted).toBe(true);
    expect(mockSubmit).toHaveBeenCalledWith('post', '/orders', undefined);
  });
});
