import { readdirSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { createValidator, type Cast, type ErrorBag } from '../../src';

interface ParityCase {
  readonly label: string;
  readonly payload: Record<string, unknown>;
  readonly expected: {
    readonly valid: boolean;
    readonly errors: ErrorBag;
  };
}

interface ParityFixture {
  readonly name: string;
  readonly slug: string;
  readonly cases: readonly ParityCase[];
}

const fixturesDir = resolve(__dirname, 'fixtures');
const jsonFiles = readdirSync(fixturesDir)
  .filter((file) => file.endsWith('.json'))
  .sort();

describe('Laravel vs Zod parity suite', () => {
  it('has dumped parity fixtures from PHP', () => {
    expect(jsonFiles.length).toBeGreaterThan(0);
  });

  for (const file of jsonFiles) {
    const fixture = JSON.parse(readFileSync(join(fixturesDir, file), 'utf8')) as ParityFixture;

    describe(fixture.name, () => {
      for (const testCase of fixture.cases) {
        it(testCase.label, async () => {
          const mod = (await import(`./fixtures/${fixture.slug}.ts`)) as { default: Cast };
          const validator = createValidator(mod.default);
          const actual = validator.validateAll(testCase.payload);

          expect(actual.valid).toBe(testCase.expected.valid);
          expect(actual.errors).toEqual(testCase.expected.errors);
        });
      }
    });
  }
});
