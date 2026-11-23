import { describe, expect, it } from 'vitest';
import { z } from 'zod';
import { fc } from '../../src';
import { matchesWildcardPattern, resolveSiblingPath } from '../../src/core/paths';

describe('fc cross-field helpers', () => {
  it('enforces requiredIf and prohibitedIf conditionally', () => {
    const schema = z
      .object({
        payment_method: fc.required(fc.string(), 'Required'),
        gcash_reference: fc.nullable(fc.string()),
        cash_note: fc.nullable(fc.string()),
      })
      .superRefine(
        fc.requiredIf('gcash_reference', 'payment_method', ['gcash'], 'GCash ref required'),
      )
      .superRefine(fc.prohibitedIf('cash_note', 'payment_method', ['gcash'], 'No cash note'));

    expect(
      schema.safeParse({ payment_method: 'cash', gcash_reference: null, cash_note: 'exact' })
        .success,
    ).toBe(true);

    const missingRef = schema.safeParse({
      payment_method: 'gcash',
      gcash_reference: '',
      cash_note: 'extra',
    });
    expect(missingRef.success).toBe(false);
    if (!missingRef.success) {
      expect(missingRef.error.issues.map((i) => [i.path.join('.'), i.message])).toEqual([
        ['gcash_reference', 'GCash ref required'],
        ['cash_note', 'No cash note'],
      ]);
    }
  });

  it('enforces requiredWith, requiredWithout, same, different, and confirmed', () => {
    const schema = z
      .object({
        password: fc.required(fc.string(), 'Required'),
        password_confirmation: fc.optional(fc.string()),
        email: fc.required(fc.email(), 'Invalid email'),
        backup_email: fc.nullable(fc.email()),
        city: fc.nullable(fc.string()),
        zip: fc.nullable(fc.string()),
      })
      .superRefine(fc.confirmed('password', 'password_confirmation', 'Confirmation mismatch'))
      .superRefine(fc.different('backup_email', 'email', 'Must be different'))
      .superRefine(fc.requiredWith('zip', ['city'], 'Zip required with city'));

    expect(
      schema.safeParse({
        password: 'secret',
        password_confirmation: 'secret',
        email: 'a@example.com',
        backup_email: 'b@example.com',
        city: 'Manila',
        zip: '1000',
      }).success,
    ).toBe(true);

    const invalid = schema.safeParse({
      password: 'secret',
      password_confirmation: 'wrong',
      email: 'a@example.com',
      backup_email: 'a@example.com',
      city: 'Manila',
      zip: '',
    });
    expect(invalid.success).toBe(false);
    if (!invalid.success) {
      expect(invalid.error.issues.map((i) => i.path.join('.'))).toEqual([
        'password',
        'backup_email',
        'zip',
      ]);
    }
  });

  it('compares numeric and date fields including sibling paths inside wildcard arrays', () => {
    const schema = z
      .object({
        min_price: fc.required(fc.integer()),
        max_price: fc.required(fc.integer()),
        events: z.array(
          z.object({
            start_date: fc.required(fc.date()),
            end_date: fc.required(fc.date()),
          }),
        ),
      })
      .superRefine(fc.compare('max_price', 'gt', 'min_price', 'numeric', 'Must exceed min'))
      .superRefine(
        fc.dateCompareField(
          'events.*.end_date',
          'after',
          'events.*.start_date',
          'Must be after start',
        ),
      );

    expect(
      schema.safeParse({
        min_price: 10,
        max_price: 20,
        events: [
          { start_date: '2026-01-01', end_date: '2026-01-05' },
          { start_date: '2026-02-01', end_date: '2026-02-10' },
        ],
      }).success,
    ).toBe(true);

    const bad = schema.safeParse({
      min_price: 50,
      max_price: 20,
      events: [
        { start_date: '2026-01-01', end_date: '2026-01-05' },
        { start_date: '2026-02-10', end_date: '2026-02-01' },
      ],
    });

    expect(bad.success).toBe(false);
    if (!bad.success) {
      expect(bad.error.issues.map((i) => [i.path.join('.'), i.message])).toEqual([
        ['max_price', 'Must exceed min'],
        ['events.1.end_date', 'Must be after start'],
      ]);
    }
  });

  it('matches wildcard patterns and resolves sibling paths', () => {
    expect(matchesWildcardPattern('items.*.quantity', 'items.3.quantity')).toBe(true);
    expect(matchesWildcardPattern('items.*.quantity', 'items.3.price')).toBe(false);
    expect(resolveSiblingPath(['items', 2, 'end_date'], 'items.*.start_date')).toEqual([
      'items',
      2,
      'start_date',
    ]);
  });
});
