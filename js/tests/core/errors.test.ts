import { describe, expect, it } from 'vitest';
import { formatIssueMessage, toErrorBag } from '../../src';

describe('errors', () => {
  it('maps zod issues to inertia dot-key error bags keeping only the first error per field', () => {
    const bag = toErrorBag([
      { path: ['customer_email'], message: 'Email is required.' },
      { path: ['customer_email'], message: 'Email must be valid.' },
      {
        path: ['items', 0, 'quantity'],
        message: 'The items.*.quantity field must not be greater than 99.',
      },
    ]);

    expect(bag).toEqual({
      customer_email: 'Email is required.',
      'items.0.quantity': 'The items.0.quantity field must not be greater than 99.',
    });
  });

  it('replaces :index and :position placeholders for wildcard paths', () => {
    expect(
      formatIssueMessage('Row :position (index :index) is required.', ['items', 2, 'sku']),
    ).toBe('Row 3 (index 2) is required.');
  });
});
