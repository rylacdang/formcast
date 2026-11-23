export type PathSegment = string | number;

/**
 * Retrieve a nested value from an object/array using a dot-separated path or segment array.
 */
export function getByPath(data: unknown, path: string | readonly PathSegment[]): unknown {
  const segments = typeof path === 'string' ? path.split('.') : path;
  let current: unknown = data;

  for (const segment of segments) {
    if (current === null || current === undefined || typeof current !== 'object') {
      return undefined;
    }

    const key = String(segment);
    if (Array.isArray(current)) {
      const idx = Number(key);
      if (!Number.isInteger(idx) || idx < 0 || idx >= current.length) {
        return undefined;
      }
      current = current[idx];
    } else {
      current = (current as Record<string, unknown>)[key];
    }
  }

  return current;
}

/**
 * Expand a wildcard path pattern (e.g. `items.*.end_date`) into all concrete segment paths
 * present in `data` (e.g. `[['items', 0, 'end_date'], ['items', 1, 'end_date']]`).
 */
export function expandWildcardPaths(data: unknown, pattern: string): PathSegment[][] {
  const segments = pattern.split('.');
  const results: PathSegment[][] = [];

  const walk = (current: unknown, index: number, acc: PathSegment[]): void => {
    if (index >= segments.length) {
      results.push(acc);
      return;
    }

    const segment = segments[index];
    if (segment === undefined) {
      return;
    }

    if (segment === '*') {
      if (Array.isArray(current)) {
        for (let i = 0; i < current.length; i++) {
          walk(current[i], index + 1, [...acc, i]);
        }
      }
      return;
    }

    const next =
      current !== null && current !== undefined && typeof current === 'object'
        ? (current as Record<string, unknown>)[segment]
        : undefined;

    walk(next, index + 1, [...acc, segment]);
  };

  walk(data, 0, []);

  return results;
}

/**
 * Resolve a sibling wildcard pattern using the numeric indices captured from a concrete path.
 *
 * Example: concretePath = ['items', 2, 'end_date'], targetPattern = 'items.*.start_date'
 * -> ['items', 2, 'start_date']
 */
export function resolveSiblingPath(
  concretePath: readonly PathSegment[],
  targetPattern: string,
): PathSegment[] {
  const indices = concretePath.filter((seg): seg is number => typeof seg === 'number');
  const targetSegments = targetPattern.split('.');
  let idxCursor = 0;

  return targetSegments.map((segment) => {
    if (segment === '*') {
      const resolved = indices[idxCursor];
      idxCursor++;
      return resolved ?? 0;
    }
    return segment;
  });
}

/**
 * Test whether a concrete dot path (e.g. `items.2.quantity`) matches a pattern
 * that may contain `*` wildcards (e.g. `items.*.quantity`).
 */
export function matchesWildcardPattern(pattern: string, concretePath: string): boolean {
  if (pattern === concretePath) {
    return true;
  }

  const patternParts = pattern.split('.');
  const concreteParts = concretePath.split('.');

  if (patternParts.length !== concreteParts.length) {
    return false;
  }

  for (let i = 0; i < patternParts.length; i++) {
    if (patternParts[i] !== '*' && patternParts[i] !== concreteParts[i]) {
      return false;
    }
  }

  return true;
}
