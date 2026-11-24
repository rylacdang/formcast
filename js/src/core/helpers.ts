import { z } from 'zod';
import { crossFieldHelpers } from './cross-field';
import { type FileLike } from './types';

const IPV4_REGEX =
  /^(?:(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)\.){3}(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)$/;

const IPV6_REGEX =
  /^(([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:))$/;

const IMAGE_EXTENSIONS = new Set(['jpg', 'jpeg', 'png', 'bmp', 'gif', 'webp', 'svg', 'avif']);

/**
 * Determine whether a value is considered empty/missing under Laravel's validation semantics
 * (including TrimStrings + ConvertEmptyStringsToNull middleware).
 */
export function isMissing(value: unknown): boolean {
  if (value === undefined || value === null) {
    return true;
  }

  if (typeof value === 'string') {
    return value.trim() === '';
  }

  if (Array.isArray(value)) {
    return value.length === 0;
  }

  return false;
}

function isFileLike(value: unknown): value is FileLike {
  if (typeof value !== 'object' || value === null) {
    return false;
  }

  if (typeof File !== 'undefined' && value instanceof File) {
    return true;
  }

  if (typeof Blob !== 'undefined' && value instanceof Blob) {
    return true;
  }

  const candidate = value as Record<string, unknown>;
  return typeof candidate['size'] === 'number' && Number.isFinite(candidate['size']);
}

function getFileExtension(file: FileLike): string {
  const name = file.name ?? '';
  const dot = name.lastIndexOf('.');
  return dot >= 0 ? name.slice(dot + 1).toLowerCase() : '';
}

function parseDateValue(value: unknown): Date | null {
  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : value;
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
    const year = Number(dateOnly[1]);
    const month = Number(dateOnly[2]);
    const day = Number(dateOnly[3]);
    const parsed = new Date(Date.UTC(year, month - 1, day));
    if (
      parsed.getUTCFullYear() === year &&
      parsed.getUTCMonth() === month - 1 &&
      parsed.getUTCDate() === day
    ) {
      return parsed;
    }
    return null;
  }

  const timestamp = Date.parse(trimmed);
  return Number.isNaN(timestamp) ? null : new Date(timestamp);
}

function startOfToday(): Date {
  const now = new Date();
  return new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()));
}

function addDays(date: Date, days: number): Date {
  return new Date(date.getTime() + days * 86_400_000);
}

export interface ChainableNumericSchema extends z.ZodType<number> {
  min(value: number, message?: string): ChainableNumericSchema;
  max(value: number, message?: string): ChainableNumericSchema;
  multipleOf(value: number, message?: string): ChainableNumericSchema;
}

function createNumericSchema(integer: boolean, message?: string): ChainableNumericSchema {
  const wrap = (inner: z.ZodNumber): ChainableNumericSchema => {
    const schema = z.preprocess((val) => {
      if (typeof val === 'number') {
        return Number.isFinite(val) ? val : val;
      }

      if (typeof val === 'string') {
        const trimmed = val.trim();
        if (trimmed === '') {
          return val;
        }

        if (integer) {
          if (!/^[+-]?\d+$/.test(trimmed)) {
            return val;
          }
          return Number(trimmed);
        }

        if (!/^[+-]?(?:\d+\.?\d*|\.\d+)$/.test(trimmed)) {
          return val;
        }
        return Number(trimmed);
      }

      return val;
    }, inner);

    return Object.assign(schema, {
      min: (value: number, msg?: string) => wrap(inner.min(value, { error: msg })),
      max: (value: number, msg?: string) => wrap(inner.max(value, { error: msg })),
      multipleOf: (value: number, msg?: string) => wrap(inner.multipleOf(value, { error: msg })),
    });
  };

  const base = integer
    ? z.number({ error: message }).int({ error: message })
    : z.number({ error: message });

  return wrap(base);
}

type DateFactory = ((message?: string) => z.ZodType<string | Date>) & {
  today: () => Date;
  tomorrow: () => Date;
  yesterday: () => Date;
  now: () => Date;
};

const dateHelper: DateFactory = Object.assign(
  (message?: string): z.ZodType<string | Date> =>
    z.custom<string | Date>((val) => parseDateValue(val) !== null, { error: message }),
  {
    today: () => startOfToday(),
    tomorrow: () => addDays(startOfToday(), 1),
    yesterday: () => addDays(startOfToday(), -1),
    now: () => new Date(),
  },
);

export const fc = {
  // --- Presence wrappers ---

  required<T extends z.ZodType>(schema: T, message?: string): z.ZodType<z.output<T>> {
    return z.unknown().superRefine((val, ctx) => {
      if (isMissing(val)) {
        ctx.addIssue({ code: 'custom', message: message ?? 'This field is required.' });
        return;
      }

      const result = schema.safeParse(val);
      if (!result.success) {
        for (const issue of result.error.issues) {
          ctx.addIssue({
            code: 'custom',
            path: issue.path,
            message: issue.message,
          });
        }
      }
    }) as z.ZodType<z.output<T>>;
  },

  nullable<T extends z.ZodType>(schema: T): z.ZodType<z.output<T> | null | undefined> {
    return z
      .any()
      .optional()
      .superRefine((val, ctx) => {
        if (val === undefined || val === null || (typeof val === 'string' && val.trim() === '')) {
          return;
        }

        const result = schema.safeParse(val);
        if (!result.success) {
          for (const issue of result.error.issues) {
            ctx.addIssue({
              code: 'custom',
              path: issue.path,
              message: issue.message,
            });
          }
        }
      });
  },

  optional<T extends z.ZodType>(schema: T): z.ZodType<z.output<T> | null | undefined> {
    return fc.nullable(schema);
  },

  sometimes<T extends z.ZodType>(schema: T): z.ZodType<z.output<T> | undefined> {
    return z
      .any()
      .optional()
      .superRefine((val, ctx) => {
        if (val === undefined) {
          return;
        }

        const result = schema.safeParse(val);
        if (!result.success) {
          for (const issue of result.error.issues) {
            ctx.addIssue({
              code: 'custom',
              path: issue.path,
              message: issue.message,
            });
          }
        }
      });
  },

  filled<T extends z.ZodType>(schema: T, message?: string): z.ZodType<z.output<T> | undefined> {
    return z
      .any()
      .optional()
      .superRefine((val, ctx) => {
        if (val === undefined) {
          return;
        }

        if (isMissing(val)) {
          ctx.addIssue({ code: 'custom', message: message ?? 'This field must have a value.' });
          return;
        }

        const result = schema.safeParse(val);
        if (!result.success) {
          for (const issue of result.error.issues) {
            ctx.addIssue({
              code: 'custom',
              path: issue.path,
              message: issue.message,
            });
          }
        }
      });
  },

  present<T extends z.ZodType>(schema: T, message?: string): z.ZodType<z.output<T> | null> {
    return z.unknown().superRefine((val, ctx) => {
      if (val === undefined) {
        ctx.addIssue({ code: 'custom', message: message ?? 'This field must be present.' });
        return;
      }

      if (val === null || (typeof val === 'string' && val.trim() === '')) {
        return;
      }

      const result = schema.safeParse(val);
      if (!result.success) {
        for (const issue of result.error.issues) {
          ctx.addIssue({
            code: 'custom',
            path: issue.path,
            message: issue.message,
          });
        }
      }
    }) as z.ZodType<z.output<T> | null>;
  },

  prohibited(message?: string): z.ZodType {
    return z
      .any()
      .optional()
      .superRefine((val, ctx) => {
        if (!isMissing(val)) {
          ctx.addIssue({ code: 'custom', message: message ?? 'This field is prohibited.' });
        }
      });
  },

  missing(message?: string): z.ZodType {
    return z
      .any()
      .optional()
      .superRefine((val, ctx) => {
        if (val !== undefined) {
          ctx.addIssue({ code: 'custom', message: message ?? 'This field must be missing.' });
        }
      });
  },

  // --- Type builders ---

  string(message?: string): z.ZodString {
    return z.string({ error: message }).trim();
  },

  email(message?: string): z.ZodEmail {
    return z.email({ error: message });
  },

  integer(message?: string): ChainableNumericSchema {
    return createNumericSchema(true, message);
  },

  numeric(message?: string): ChainableNumericSchema {
    return createNumericSchema(false, message);
  },

  boolean(message?: string): z.ZodType<boolean> {
    return z.preprocess(
      (val) => {
        if (val === true || val === 1 || val === '1') {
          return true;
        }
        if (val === false || val === 0 || val === '0') {
          return false;
        }
        return val;
      },
      z.boolean({ error: message }),
    );
  },

  accepted(message?: string): z.ZodType {
    const acceptedValues = new Set<unknown>(['yes', 'on', 1, '1', true, 'true']);
    return z.custom((val) => acceptedValues.has(val), { error: message });
  },

  declined(message?: string): z.ZodType {
    const declinedValues = new Set<unknown>(['no', 'off', 0, '0', false, 'false']);
    return z.custom((val) => declinedValues.has(val), { error: message });
  },

  decimal(min: number, max?: number, message?: string): z.ZodType<string | number> {
    return z.custom<string | number>(
      (val) => {
        const str =
          typeof val === 'number'
            ? Number.isFinite(val)
              ? String(val)
              : ''
            : typeof val === 'string'
              ? val.trim()
              : '';

        if (!/^[+-]?\d+(?:\.\d+)?$/.test(str)) {
          return false;
        }

        const dot = str.indexOf('.');
        const decimals = dot === -1 ? 0 : str.length - dot - 1;
        return max === undefined ? decimals === min : decimals >= min && decimals <= max;
      },
      { error: message },
    );
  },

  json(message?: string): z.ZodType<string> {
    return z.string({ error: message }).refine(
      (val) => {
        try {
          JSON.parse(val);
          return true;
        } catch {
          return false;
        }
      },
      { error: message },
    );
  },

  in(values: readonly string[], message?: string): z.ZodType<string> {
    return z.custom<string>(
      (val) => (typeof val === 'string' || typeof val === 'number') && values.includes(String(val)),
      { error: message },
    );
  },

  notIn(values: readonly string[]): (val: unknown) => boolean {
    return (val: unknown) => !values.includes(String(val));
  },

  file(message?: string): z.ZodType<FileLike> {
    return z.custom<FileLike>((val) => isFileLike(val), { error: message });
  },

  date: dateHelper,

  // --- File predicates ---

  fileMinKb(minKb: number): (file: FileLike) => boolean {
    return (file: FileLike) => file.size >= minKb * 1024;
  },

  fileMaxKb(maxKb: number): (file: FileLike) => boolean {
    return (file: FileLike) => file.size <= maxKb * 1024;
  },

  fileSizeKb(kb: number): (file: FileLike) => boolean {
    return (file: FileLike) => Math.round(file.size / 1024) === kb;
  },

  fileBetweenKb(minKb: number, maxKb: number): (file: FileLike) => boolean {
    return (file: FileLike) => file.size >= minKb * 1024 && file.size <= maxKb * 1024;
  },

  isImage: (file: FileLike): boolean => {
    if (file.type && file.type.toLowerCase().startsWith('image/')) {
      return true;
    }

    return IMAGE_EXTENSIONS.has(getFileExtension(file));
  },

  fileMimes(mimes: readonly string[]): (file: FileLike) => boolean {
    const normalized = new Set(mimes.map((m) => m.toLowerCase()));
    return (file: FileLike) => {
      const ext = getFileExtension(file);
      if (ext !== '' && normalized.has(ext)) {
        return true;
      }

      if (file.type) {
        const sub = file.type.split('/')[1]?.toLowerCase() ?? '';
        if (normalized.has(sub)) {
          return true;
        }
      }

      return false;
    };
  },

  fileMimetypes(types: readonly string[]): (file: FileLike) => boolean {
    const normalized = types.map((t) => t.toLowerCase());
    return (file: FileLike) => {
      const mime = (file.type ?? '').toLowerCase();
      return normalized.some((allowed) => {
        if (allowed.endsWith('/*')) {
          return mime.startsWith(allowed.slice(0, -1));
        }
        return mime === allowed;
      });
    };
  },

  fileExtensions(extensions: readonly string[]): (file: FileLike) => boolean {
    const normalized = new Set(extensions.map((e) => e.toLowerCase()));
    return (file: FileLike) => normalized.has(getFileExtension(file));
  },

  // --- Digit, format & date predicates ---

  isDigits(length: number): (val: unknown) => boolean {
    return (val: unknown) => /^\d+$/.test(String(val)) && String(val).length === length;
  },

  isDigitsBetween(min: number, max: number): (val: unknown) => boolean {
    return (val: unknown) => {
      const str = String(val);
      return /^\d+$/.test(str) && str.length >= min && str.length <= max;
    };
  },

  isMinDigits(min: number): (val: unknown) => boolean {
    return (val: unknown) => /^\d+$/.test(String(val)) && String(val).length >= min;
  },

  isMaxDigits(max: number): (val: unknown) => boolean {
    return (val: unknown) => /^\d+$/.test(String(val)) && String(val).length <= max;
  },

  isIp: (val: string): boolean => IPV4_REGEX.test(val) || IPV6_REGEX.test(val),

  isIpv4: (val: string): boolean => IPV4_REGEX.test(val),

  isIpv6: (val: string): boolean => IPV6_REGEX.test(val),

  startsWith(prefixes: readonly string[]): (val: string) => boolean {
    return (val: string) => prefixes.some((prefix) => val.startsWith(prefix));
  },

  endsWith(suffixes: readonly string[]): (val: string) => boolean {
    return (val: string) => suffixes.some((suffix) => val.endsWith(suffix));
  },

  doesntStartWith(prefixes: readonly string[]): (val: string) => boolean {
    return (val: string) => prefixes.every((prefix) => !val.startsWith(prefix));
  },

  doesntEndWith(suffixes: readonly string[]): (val: string) => boolean {
    return (val: string) => suffixes.every((suffix) => !val.endsWith(suffix));
  },

  isMixedCase: (val: string): boolean => /\p{Ll}/u.test(val) && /\p{Lu}/u.test(val),

  dateFormat(formats: readonly string[]): (val: unknown) => boolean {
    return (val: unknown) => {
      if (typeof val !== 'string') {
        return false;
      }

      return formats.some((format) => {
        const pattern = format
          .replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
          .replace(/Y/g, '\\d{4}')
          .replace(/m/g, '(?:0[1-9]|1[0-2])')
          .replace(/d/g, '(?:0[1-9]|[12]\\d|3[01])')
          .replace(/H/g, '(?:[01]\\d|2[0-3])')
          .replace(/i/g, '[0-5]\\d')
          .replace(/s/g, '[0-5]\\d');

        return new RegExp(`^${pattern}$`).test(val.trim());
      });
    };
  },

  dateCompare(
    op: 'equals' | 'after' | 'afterOrEqual' | 'before' | 'beforeOrEqual',
    target: string | (() => Date),
  ): (val: unknown) => boolean {
    return (val: unknown) => {
      const actual = parseDateValue(val);
      const expected = typeof target === 'function' ? target() : parseDateValue(target);

      if (actual === null || expected === null) {
        return false;
      }

      const a = actual.getTime();
      const b = expected.getTime();

      switch (op) {
        case 'equals':
          return a === b;
        case 'after':
          return a > b;
        case 'afterOrEqual':
          return a >= b;
        case 'before':
          return a < b;
        case 'beforeOrEqual':
          return a <= b;
      }
    };
  },

  ...crossFieldHelpers,
};
