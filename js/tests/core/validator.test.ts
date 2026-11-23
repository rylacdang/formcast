import { describe, expect, it } from 'vitest';
import { z } from 'zod';
import { createValidator, defineCast, fc } from '../../src';

const OrderCast = defineCast({
  name: 'StoreOrderRequest',
  schema: z
    .object({
      customer_email: fc.required(fc.email('Invalid email'), 'Email required'),
      payment_method: fc.required(fc.in(['cash', 'gcash'], 'Bad method'), 'Method required'),
      gcash_reference: fc.nullable(fc.string().length(13, 'Must be 13 chars')),
      items: z.array(
        z.object({
          product_id: fc.required(fc.integer(), 'Product required'),
          quantity: fc.required(
            fc
              .integer()
              .min(1, 'Min 1')
              .max(99, 'The items.*.quantity field must not be greater than 99.'),
            'Quantity required',
          ),
        }),
      ),
    })
    .superRefine(
      fc.requiredIf('gcash_reference', 'payment_method', ['gcash'], 'GCash ref is required.'),
    ),
  serverFields: ['customer_email', 'items.*.product_id'],
  dependencies: {
    payment_method: ['gcash_reference'],
  },
  usedBy: [],
});

describe('createValidator', () => {
  it('validates all fields and returns inertia-shaped dot-key error bags', () => {
    const validator = createValidator(OrderCast);

    const invalid = validator.validateAll({
      customer_email: 'not-an-email',
      payment_method: 'gcash',
      gcash_reference: '',
      items: [{ product_id: 1, quantity: 150 }],
    });

    expect(invalid.valid).toBe(false);
    expect(invalid.errors).toEqual({
      customer_email: 'Invalid email',
      gcash_reference: 'GCash ref is required.',
      'items.0.quantity': 'The items.0.quantity field must not be greater than 99.',
    });

    const valid = validator.validateAll({
      customer_email: 'buyer@example.com',
      payment_method: 'cash',
      gcash_reference: null,
      items: [{ product_id: 1, quantity: 2 }],
    });

    expect(valid.valid).toBe(true);
    expect(valid.errors).toEqual({});
  });

  it('validates a single field, revalidates dependents, and signals needsServer only after client passes', () => {
    const validator = createValidator(OrderCast);

    // Client fails on customer_email -> needsServer must be false
    const badEmail = validator.validateField('customer_email', {
      customer_email: 'bad',
      payment_method: 'cash',
      gcash_reference: null,
      items: [{ product_id: 1, quantity: 1 }],
    });
    expect(badEmail.valid).toBe(false);
    expect(badEmail.errors).toEqual({ customer_email: 'Invalid email' });
    expect(badEmail.needsServer).toBe(false);

    // Client passes on customer_email -> needsServer is true
    const goodEmail = validator.validateField('customer_email', {
      customer_email: 'buyer@example.com',
      payment_method: 'cash',
      gcash_reference: null,
      items: [{ product_id: 1, quantity: 1 }],
    });
    expect(goodEmail.valid).toBe(true);
    expect(goodEmail.cleared).toEqual(['customer_email']);
    expect(goodEmail.needsServer).toBe(true);

    // Changing payment_method to gcash revalidates dependent gcash_reference
    const dependentTriggered = validator.validateField('payment_method', {
      customer_email: 'buyer@example.com',
      payment_method: 'gcash',
      gcash_reference: '',
      items: [{ product_id: 1, quantity: 1 }],
    });
    expect(dependentTriggered.valid).toBe(false);
    expect(dependentTriggered.errors).toEqual({ gcash_reference: 'GCash ref is required.' });
    expect(dependentTriggered.cleared).toEqual(['payment_method']);
    expect(dependentTriggered.needsServer).toBe(false);
  });
});
