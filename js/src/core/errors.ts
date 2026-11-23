import { type ErrorBag } from './types';

export interface ZodIssueLike {
  readonly path: readonly PropertyKey[];
  readonly message: string;
}

/**
 * Format a dot-notation path key from a Zod issue path array.
 */
export function formatIssuePath(path: readonly PropertyKey[]): string {
  return path.map((segment) => String(segment)).join('.');
}

/**
 * Replace `:index`, `:position`, and wildcard attribute placeholders in a validation message
 * using the numeric indices from the issue's concrete path.
 */
export function formatIssueMessage(message: string, path: readonly PropertyKey[]): string {
  const numericIndices = path
    .map((segment) => {
      if (typeof segment === 'number') {
        return segment;
      }
      if (typeof segment === 'string' && /^\d+$/.test(segment)) {
        return Number(segment);
      }
      return null;
    })
    .filter((index): index is number => index !== null);

  if (numericIndices.length === 0) {
    return message;
  }

  const firstIndex = numericIndices[0] ?? 0;
  let formatted = message
    .replaceAll(':index', String(firstIndex))
    .replaceAll(':position', String(firstIndex + 1));

  if (formatted.includes('.*')) {
    let cursor = 0;
    formatted = formatted.replace(/\.\*/g, () => {
      const idx = numericIndices[cursor];
      cursor++;
      return idx !== undefined ? `.${String(idx)}` : '.*';
    });
  }

  return formatted;
}

/**
 * Convert Zod validation issues into an Inertia-compatible error bag:
 * dot-notation keys with only the first error message per field.
 */
export function toErrorBag(issues: readonly ZodIssueLike[]): ErrorBag {
  const errors: ErrorBag = {};

  for (const issue of issues) {
    const key = formatIssuePath(issue.path);
    if (key === '' || key in errors) {
      continue;
    }

    errors[key] = formatIssueMessage(issue.message, issue.path);
  }

  return errors;
}
