import { describe, expect, it } from 'vitest';
import { z } from 'zod';
import { defineCast, fc, isMissing } from '../../src';

describe('fc presence helpers', () => {
  it('treats undefined, null, empty string, whitespace, and empty array as missing', () => {
    expect(isMissing(undefined)).toBe(true);
    expect(isMissing(null)).toBe(true);
    expect(isMissing('')).toBe(true);
    expect(isMissing('   ')).toBe(true);
    expect(isMissing([])).toBe(true);

    expect(isMissing(0)).toBe(false);
    expect(isMissing(false)).toBe(false);
    expect(isMissing('a')).toBe(false);
    expect(isMissing([1])).toBe(false);
  });

  it('enforces fc.required and trims strings like TrimStrings', () => {
    const schema = fc.required(fc.string().max(5, 'Too long'), 'Field is required');

    expect(schema.safeParse('').success).toBe(false);
    expect(schema.safeParse('   ').success).toBe(false);
    expect(schema.safeParse(null).success).toBe(false);
    expect(schema.safeParse(undefined).success).toBe(false);

    const err = schema.safeParse('');
    expect(err.success).toBe(false);
    if (!err.success) {
      expect(err.error.issues[0]?.message).toBe('Field is required');
    }

    expect(schema.safeParse('  hello  ').success).toBe(true);
    expect(schema.safeParse('123456').success).toBe(false);
  });

  it('allows empty strings and null for fc.nullable', () => {
    const schema = fc.nullable(fc.string().length(13, 'Must be 13 chars'));

    expect(schema.safeParse(null).success).toBe(true);
    expect(schema.safeParse(undefined).success).toBe(true);
    expect(schema.safeParse('').success).toBe(true);
    expect(schema.safeParse('   ').success).toBe(true);
    expect(schema.safeParse('12345').success).toBe(false);
    expect(schema.safeParse('1234567890123').success).toBe(true);
  });
});

describe('fc numeric, integer, and boolean helpers', () => {
  it('matches laravel integer semantics (accepts "5", rejects "" and "5.0")', () => {
    const schema = fc.integer('Must be int').min(1, 'Min 1').max(99, 'Max 99');

    expect(schema.safeParse(5).success).toBe(true);
    expect(schema.safeParse('5').success).toBe(true);
    expect(schema.safeParse(' 42 ').success).toBe(true);

    expect(schema.safeParse('').success).toBe(false);
    expect(schema.safeParse('5.0').success).toBe(false);
    expect(schema.safeParse(5.5).success).toBe(false);
    expect(schema.safeParse(0).success).toBe(false);
    expect(schema.safeParse(100).success).toBe(false);
  });

  it('matches laravel numeric semantics (accepts "12.5", rejects "")', () => {
    const schema = fc.numeric('Must be numeric').min(0.5, 'Too small');

    expect(schema.safeParse(1.5).success).toBe(true);
    expect(schema.safeParse('12.5').success).toBe(true);
    expect(schema.safeParse('').success).toBe(false);
    expect(schema.safeParse('0.1').success).toBe(false);
    expect(schema.safeParse('abc').success).toBe(false);
  });

  it('matches laravel boolean semantics (true/false/1/0/"1"/"0")', () => {
    const schema = fc.boolean('Must be boolean');

    for (const valid of [true, false, 1, 0, '1', '0']) {
      expect(schema.safeParse(valid).success).toBe(true);
    }

    for (const invalid of ['yes', 'true', 'false', 2, '']) {
      expect(schema.safeParse(invalid).success).toBe(false);
    }
  });
});

describe('fc file helpers and defineCast', () => {
  it('validates file kilobyte sizes, image detection, mimes, and extensions', () => {
    const schema = fc
      .file('Must be a file')
      .refine(fc.isImage, 'Must be image')
      .refine(fc.fileMaxKb(100), 'Too large')
      .refine(fc.fileExtensions(['png', 'jpg']), 'Bad ext');

    expect(
      schema.safeParse({ name: 'avatar.png', size: 50 * 1024, type: 'image/png' }).success,
    ).toBe(true);

    expect(
      schema.safeParse({ name: 'avatar.png', size: 150 * 1024, type: 'image/png' }).success,
    ).toBe(false);

    expect(
      schema.safeParse({ name: 'doc.pdf', size: 10 * 1024, type: 'application/pdf' }).success,
    ).toBe(false);
  });

  it('freezes metadata returned by defineCast', () => {
    const schema = z.object({ email: fc.required(fc.email()) });
    const cast = defineCast({
      name: 'SampleRequest',
      schema,
      serverFields: ['email'],
      dependencies: {},
      usedBy: [],
    });

    expect(cast.name).toBe('SampleRequest');
    expect(cast.serverFields).toEqual(['email']);
    expect(Object.isFrozen(cast)).toBe(true);
  });
});
