import { describe, expect, it } from 'vitest';
import { getDependents, isServerField } from '../../src';

describe('dependency graph', () => {
  it('resolves direct, transitive, cyclic, and wildcard sibling dependencies', () => {
    const deps = {
      payment_method: ['gcash_reference'],
      password: ['password_confirmation'],
      password_confirmation: ['password'],
      'items.*.start_date': ['items.*.end_date'],
    };

    expect(getDependents(deps, 'payment_method')).toEqual(['gcash_reference']);
    expect(getDependents(deps, 'password')).toEqual(['password_confirmation']);
    expect(getDependents(deps, 'items.2.start_date')).toEqual(['items.2.end_date']);
  });

  it('matches server fields for exact and wildcard paths', () => {
    const serverFields = ['customer_email', 'items.*.product_id'];

    expect(isServerField(serverFields, 'customer_email')).toBe(true);
    expect(isServerField(serverFields, 'items.0.product_id')).toBe(true);
    expect(isServerField(serverFields, 'items.0.quantity')).toBe(false);
  });
});
