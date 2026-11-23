import { getDependents, isServerField } from './dependency-graph';
import { toErrorBag } from './errors';
import { type Cast, type CastOutput, type ErrorBag } from './types';

export interface ValidationResult<TOutput = unknown> {
  readonly valid: boolean;
  readonly errors: ErrorBag;
  readonly data?: TOutput;
}

export interface FieldValidationResult {
  readonly valid: boolean;
  readonly errors: ErrorBag;
  readonly cleared: readonly string[];
  readonly needsServer: boolean;
}

export interface FormcastValidator<TCast extends Cast = Cast> {
  readonly cast: TCast;
  validateAll(data: unknown): ValidationResult<CastOutput<TCast>>;
  validateField(path: string, data: unknown): FieldValidationResult;
  isServerField(path: string): boolean;
  getDependents(path: string): string[];
}

export function createValidator<TCast extends Cast>(cast: TCast): FormcastValidator<TCast> {
  const validateAll = (data: unknown): ValidationResult<CastOutput<TCast>> => {
    const parsed = cast.schema.safeParse(data);

    if (parsed.success) {
      return {
        valid: true,
        errors: {},
        data: parsed.data as CastOutput<TCast>,
      };
    }

    return {
      valid: false,
      errors: toErrorBag(parsed.error.issues),
    };
  };

  const validateField = (path: string, data: unknown): FieldValidationResult => {
    const dependents = getDependents(cast.dependencies, path);
    const affectedFields = [path, ...dependents];
    const allResult = validateAll(data);

    const errors: ErrorBag = {};
    const cleared: string[] = [];

    for (const field of affectedFields) {
      const message = allResult.errors[field];
      if (message !== undefined) {
        errors[field] = message;
      } else {
        cleared.push(field);
      }
    }

    const fieldValid = !(path in errors);

    return {
      valid: Object.keys(errors).length === 0,
      errors,
      cleared,
      needsServer: fieldValid && isServerField(cast.serverFields, path),
    };
  };

  return {
    cast,
    validateAll,
    validateField,
    isServerField: (path: string) => isServerField(cast.serverFields, path),
    getDependents: (path: string) => getDependents(cast.dependencies, path),
  };
}
