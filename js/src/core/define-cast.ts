import { type z } from 'zod';
import { type Cast, type CastDefinition } from './types';

export function defineCast<TSchema extends z.ZodType>(
  definition: CastDefinition<TSchema>,
): Cast<TSchema> {
  return Object.freeze({
    name: definition.name,
    schema: definition.schema,
    serverFields: Object.freeze([...definition.serverFields]),
    dependencies: Object.freeze({ ...definition.dependencies }),
    usedBy: Object.freeze([...definition.usedBy]),
  });
}
