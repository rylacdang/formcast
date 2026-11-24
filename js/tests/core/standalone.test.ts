import { readdirSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { z } from 'zod';
import { createValidator, defineCast, fc, toErrorBag } from '../../src';

const FORBIDDEN_CORE_IMPORTS = [
  /from\s+['"]vue['"]/,
  /from\s+['"]react['"]/,
  /from\s+['"]vite['"]/,
  /from\s+['"]@inertiajs\//,
];

describe('standalone framework-agnostic core', () => {
  it('never imports vue, react, vite, or inertia inside the core entry', () => {
    const srcRoot = resolve(__dirname, '../../src');
    const coreDir = join(srcRoot, 'core');

    const files = [
      join(srcRoot, 'index.ts'),
      ...readdirSync(coreDir)
        .filter((file) => file.endsWith('.ts'))
        .map((file) => join(coreDir, file)),
    ];

    for (const file of files) {
      const content = readFileSync(file, 'utf8');
      for (const pattern of FORBIDDEN_CORE_IMPORTS) {
        expect(content, `Forbidden framework import found in ${file}`).not.toMatch(pattern);
      }
    }
  });

  it('validates payloads and maps errors without any frontend framework', () => {
    const LoginCast = defineCast({
      name: 'LoginRequest',
      schema: z.object({
        email: fc.required(
          fc.email('The email field must be a valid email address.'),
          'The email field is required.',
        ),
        password: fc.required(
          fc.string().min(8, 'The password field must be at least 8 characters.'),
          'The password field is required.',
        ),
      }),
      serverFields: [],
      dependencies: {},
      usedBy: [],
    });

    const validator = createValidator(LoginCast);
    const result = validator.validateAll({ email: 'bad', password: '123' });

    expect(result.valid).toBe(false);
    expect(result.errors).toEqual({
      email: 'The email field must be a valid email address.',
      password: 'The password field must be at least 8 characters.',
    });

    const manual = toErrorBag([{ path: ['items', 0, 'qty'], message: 'Item :position invalid.' }]);
    expect(manual).toEqual({ 'items.0.qty': 'Item 1 invalid.' });
  });
});
