import { type z } from 'zod';
import { isMissing } from './helpers';
import { expandWildcardPaths, getByPath, resolveSiblingPath, type PathSegment } from './paths';

const ACCEPTED_VALUES = new Set<unknown>(['yes', 'on', 1, '1', true, 'true']);
const DECLINED_VALUES = new Set<unknown>(['no', 'off', 0, '0', false, 'false']);

type SuperRefineFn = (data: Record<string, unknown>, ctx: z.RefinementCtx) => void;
type CompareOperator = 'gt' | 'gte' | 'lt' | 'lte';
type SizeVariant = 'numeric' | 'string' | 'array' | 'file';
type DateCompareOp = 'equals' | 'after' | 'afterOrEqual' | 'before' | 'beforeOrEqual';

function normalizeStringValue(value: unknown): string {
  if (typeof value === 'boolean') {
    return value ? 'true' : 'false';
  }
  if (typeof value === 'number') {
    return String(value);
  }
  if (typeof value === 'string') {
    return value.trim();
  }
  return '';
}

function matchesAnyValue(value: unknown, expectedValues: readonly string[]): boolean {
  if (value === undefined || value === null) {
    return false;
  }

  const normalized = normalizeStringValue(value);

  for (const expected of expectedValues) {
    if (normalized === expected) {
      return true;
    }
    if (typeof value === 'boolean') {
      if (value && (expected === '1' || expected === 'true')) {
        return true;
      }
      if (!value && (expected === '0' || expected === 'false')) {
        return true;
      }
    }
  }

  return false;
}

function measureSize(value: unknown, variant: SizeVariant): number | null {
  if (isMissing(value)) {
    return null;
  }

  switch (variant) {
    case 'numeric': {
      const num = typeof value === 'number' ? value : Number(String(value).trim());
      return Number.isFinite(num) ? num : null;
    }
    case 'array':
      return Array.isArray(value) ? value.length : null;
    case 'file': {
      if (typeof value === 'object' && value !== null && 'size' in value) {
        const size = value.size;
        return typeof size === 'number' ? size / 1024 : null;
      }
      return null;
    }
    case 'string':
      return typeof value === 'string' ? value.trim().length : String(value).length;
  }
}

function parseComparableDate(value: unknown): number | null {
  if (value instanceof Date) {
    const time = value.getTime();
    return Number.isNaN(time) ? null : time;
  }

  if (typeof value !== 'string') {
    return null;
  }

  const trimmed = value.trim();
  if (trimmed === '') {
    return null;
  }

  const dateOnly = /^(\d{4})-(\d{2})-(\d{2})$/.exec(trimmed);
  if (dateOnly) {
    const time = Date.UTC(Number(dateOnly[1]), Number(dateOnly[2]) - 1, Number(dateOnly[3]));
    return Number.isNaN(time) ? null : time;
  }

  const time = Date.parse(trimmed);
  return Number.isNaN(time) ? null : time;
}

function forEachConcreteField(
  data: Record<string, unknown>,
  fieldPattern: string,
  callback: (path: PathSegment[], value: unknown) => void,
): void {
  const paths = expandWildcardPaths(data, fieldPattern);
  for (const path of paths) {
    callback(path, getByPath(data, path));
  }
}

export const crossFieldHelpers = {
  requiredIf(
    field: string,
    other: string,
    values: readonly string[],
    message: string,
  ): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        const otherVal = getByPath(data, resolveSiblingPath(path, other));
        if (matchesAnyValue(otherVal, values) && isMissing(value)) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  requiredUnless(
    field: string,
    other: string,
    values: readonly string[],
    message: string,
  ): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        const otherVal = getByPath(data, resolveSiblingPath(path, other));
        if (!matchesAnyValue(otherVal, values) && isMissing(value)) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  requiredIfAccepted(field: string, other: string, message: string): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        const otherVal = getByPath(data, resolveSiblingPath(path, other));
        if (ACCEPTED_VALUES.has(otherVal) && isMissing(value)) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  requiredWith(field: string, others: readonly string[], message: string): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        const anyPresent = others.some(
          (other) => !isMissing(getByPath(data, resolveSiblingPath(path, other))),
        );
        if (anyPresent && isMissing(value)) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  requiredWithAll(field: string, others: readonly string[], message: string): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        const allPresent = others.every(
          (other) => !isMissing(getByPath(data, resolveSiblingPath(path, other))),
        );
        if (allPresent && isMissing(value)) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  requiredWithout(field: string, others: readonly string[], message: string): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        const anyMissing = others.some((other) =>
          isMissing(getByPath(data, resolveSiblingPath(path, other))),
        );
        if (anyMissing && isMissing(value)) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  requiredWithoutAll(field: string, others: readonly string[], message: string): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        const allMissing = others.every((other) =>
          isMissing(getByPath(data, resolveSiblingPath(path, other))),
        );
        if (allMissing && isMissing(value)) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  prohibitedIf(
    field: string,
    other: string,
    values: readonly string[],
    message: string,
  ): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        const otherVal = getByPath(data, resolveSiblingPath(path, other));
        if (matchesAnyValue(otherVal, values) && !isMissing(value)) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  prohibitedUnless(
    field: string,
    other: string,
    values: readonly string[],
    message: string,
  ): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        const otherVal = getByPath(data, resolveSiblingPath(path, other));
        if (!matchesAnyValue(otherVal, values) && !isMissing(value)) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  missingIf(
    field: string,
    other: string,
    values: readonly string[],
    message: string,
  ): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        const otherVal = getByPath(data, resolveSiblingPath(path, other));
        if (matchesAnyValue(otherVal, values) && value !== undefined) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  acceptedIf(
    field: string,
    other: string,
    values: readonly string[],
    message: string,
  ): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        const otherVal = getByPath(data, resolveSiblingPath(path, other));
        if (matchesAnyValue(otherVal, values) && !ACCEPTED_VALUES.has(value)) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  declinedIf(
    field: string,
    other: string,
    values: readonly string[],
    message: string,
  ): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        const otherVal = getByPath(data, resolveSiblingPath(path, other));
        if (matchesAnyValue(otherVal, values) && !DECLINED_VALUES.has(value)) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  confirmed(field: string, confirmationField: string, message: string): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        if (isMissing(value)) {
          return;
        }
        const confirmVal = getByPath(data, resolveSiblingPath(path, confirmationField));
        if (value !== confirmVal) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  same(field: string, other: string, message: string): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        if (isMissing(value)) {
          return;
        }
        const otherVal = getByPath(data, resolveSiblingPath(path, other));
        if (value !== otherVal) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  different(field: string, other: string, message: string): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        if (isMissing(value)) {
          return;
        }
        const otherVal = getByPath(data, resolveSiblingPath(path, other));
        if (value === otherVal) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  inArray(field: string, other: string, message: string): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        if (isMissing(value)) {
          return;
        }
        const otherPaths = expandWildcardPaths(data, other);
        const allowed = otherPaths.map((p) => getByPath(data, p));
        if (!allowed.includes(value)) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  compare(
    field: string,
    operator: CompareOperator,
    other: string,
    variant: SizeVariant,
    message: string,
  ): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        const left = measureSize(value, variant);
        const right = measureSize(getByPath(data, resolveSiblingPath(path, other)), variant);

        if (left === null || right === null) {
          return;
        }

        const ok =
          operator === 'gt'
            ? left > right
            : operator === 'gte'
              ? left >= right
              : operator === 'lt'
                ? left < right
                : left <= right;

        if (!ok) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },

  dateCompareField(
    field: string,
    operator: DateCompareOp,
    other: string,
    message: string,
  ): SuperRefineFn {
    return (data, ctx) => {
      forEachConcreteField(data, field, (path, value) => {
        const left = parseComparableDate(value);
        const right = parseComparableDate(getByPath(data, resolveSiblingPath(path, other)));

        if (left === null || right === null) {
          return;
        }

        const ok =
          operator === 'equals'
            ? left === right
            : operator === 'after'
              ? left > right
              : operator === 'afterOrEqual'
                ? left >= right
                : operator === 'before'
                  ? left < right
                  : left <= right;

        if (!ok) {
          ctx.addIssue({ code: 'custom', path, message });
        }
      });
    };
  },
};
