import { matchesWildcardPattern, resolveSiblingPath, type PathSegment } from './paths';

function parseConcreteSegments(path: string): PathSegment[] {
  return path.split('.').map((segment) => (/^\d+$/.test(segment) ? Number(segment) : segment));
}

/**
 * Resolve all concrete fields that transitively depend on `fieldPath`,
 * supporting wildcard triggers/targets and safely handling cycles.
 */
export function getDependents(
  dependencies: Readonly<Record<string, readonly string[]>>,
  fieldPath: string,
): string[] {
  const visited = new Set<string>([fieldPath]);
  const queue: string[] = [fieldPath];
  const dependents: string[] = [];

  while (queue.length > 0) {
    const current = queue.shift();
    if (current === undefined) {
      break;
    }

    const concreteSegments = parseConcreteSegments(current);

    for (const [trigger, targets] of Object.entries(dependencies)) {
      if (!matchesWildcardPattern(trigger, current)) {
        continue;
      }

      for (const target of targets) {
        const resolved = target.includes('*')
          ? resolveSiblingPath(concreteSegments, target).join('.')
          : target;

        if (!visited.has(resolved)) {
          visited.add(resolved);
          dependents.push(resolved);
          queue.push(resolved);
        }
      }
    }
  }

  return dependents;
}

/**
 * Determine whether a concrete field path has server-only validation rules.
 */
export function isServerField(serverFields: readonly string[], fieldPath: string): boolean {
  return serverFields.some((pattern) => matchesWildcardPattern(pattern, fieldPath));
}
