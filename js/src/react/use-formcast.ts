import { useForm } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import { createValidator, type Cast, type CastInput, type FormcastValidator } from '../index';

export type HttpMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

export interface WayfinderRoute {
  readonly url: string;
  readonly method: HttpMethod;
}

export interface UseFormcastOptions {
  /**
   * When to run client-side validation on field interaction.
   * @default 'blur'
   */
  readonly validateOn?: 'blur' | 'input' | 'submit';

  /**
   * Enable Inertia Precognition for server-only fields after client rules pass.
   * Pass a Wayfinder route or `{ method, url }` to override the auto-detected `usedBy[0]` endpoint.
   * @default true
   */
  readonly precognition?: boolean | WayfinderRoute;
}

export interface InertiaReactFormBase<TData extends object> {
  data: TData;
  errors: Record<string, string | undefined>;
  hasErrors: boolean;
  processing: boolean;
  validating?: boolean;
  setData: {
    <K extends keyof TData>(key: K, value: TData[K]): void;
    (dataOrUpdater: TData | ((previousData: TData) => TData)): void;
  };
  setError(field: string, value: string): void;
  setError(errors: Record<string, string>): void;
  clearErrors(...fields: string[]): void;
  submit(method: HttpMethod, url: string, options?: Record<string, unknown>): void;
  submit(route: WayfinderRoute, options?: Record<string, unknown>): void;
  validate?(field: string): unknown;
  withPrecognition?(method: HttpMethod, url: string): InertiaReactFormBase<TData>;
}

export interface FormcastReactHelpers<TCast extends Cast> {
  readonly cast: TCast;
  readonly validator: FormcastValidator<TCast>;
  readonly validating: boolean;
  touch(field: string): void;
  touched(field?: string): boolean;
  validate(field?: string): boolean;
  invalid(field: string): boolean;
  valid(field: string): boolean;
  submit(
    methodOrRoute: HttpMethod | WayfinderRoute,
    urlOrOptions?: string | Record<string, unknown>,
    options?: Record<string, unknown>,
  ): boolean;
}

export type FormcastReactForm<
  TCast extends Cast,
  TData extends object = CastInput<TCast> & object,
> = Omit<InertiaReactFormBase<TData>, 'submit' | 'validate'> & FormcastReactHelpers<TCast>;

function resolvePrecognitionEndpoint(
  cast: Cast,
  option: boolean | WayfinderRoute | undefined,
): WayfinderRoute | null {
  if (option === false) {
    return null;
  }

  if (typeof option === 'object') {
    return option;
  }

  const firstRoute = cast.usedBy[0];
  if (!firstRoute) {
    return null;
  }

  const rawMethod = (firstRoute.methods[0] ?? 'post').toLowerCase();
  const method: HttpMethod =
    rawMethod === 'get' ||
    rawMethod === 'post' ||
    rawMethod === 'put' ||
    rawMethod === 'patch' ||
    rawMethod === 'delete'
      ? rawMethod
      : 'post';

  return { method, url: firstRoute.uri };
}

export function useFormcast<TCast extends Cast, TData extends object>(
  cast: TCast,
  initialData: TData,
  options: UseFormcastOptions = {},
): FormcastReactForm<TCast, TData> {
  const validateOn = options.validateOn ?? 'blur';
  const validator = useMemo(() => createValidator(cast), [cast]);
  const [touchedFields, setTouchedFields] = useState<ReadonlySet<string>>(() => new Set());

  const createInertiaForm = useForm as unknown as (data: object) => InertiaReactFormBase<TData>;
  let baseForm = createInertiaForm(initialData);

  const precognitionEndpoint = resolvePrecognitionEndpoint(cast, options.precognition);

  if (precognitionEndpoint !== null && typeof baseForm.withPrecognition === 'function') {
    baseForm = baseForm.withPrecognition(precognitionEndpoint.method, precognitionEndpoint.url);
  }

  const markTouched = useCallback((field: string) => {
    setTouchedFields((prev) => {
      if (prev.has(field)) {
        return prev;
      }
      const next = new Set(prev);
      next.add(field);
      return next;
    });
  }, []);

  const validateSingleField = (field: string): boolean => {
    const result = validator.validateField(field, baseForm.data);

    for (const cleared of result.cleared) {
      baseForm.clearErrors(cleared);
    }

    for (const [key, message] of Object.entries(result.errors)) {
      baseForm.setError(key, message);
    }

    if (
      result.needsServer &&
      precognitionEndpoint !== null &&
      typeof baseForm.validate === 'function'
    ) {
      baseForm.validate(field);
    }

    return result.valid;
  };

  const validateAllFields = (): boolean => {
    const result = validator.validateAll(baseForm.data);
    baseForm.clearErrors();

    if (!result.valid) {
      for (const [key, message] of Object.entries(result.errors)) {
        baseForm.setError(key, message);
      }
      return false;
    }

    return true;
  };

  const invalid = (field: string): boolean => {
    const error = baseForm.errors[field];
    return typeof error === 'string' && error.length > 0;
  };

  const helpers: FormcastReactHelpers<TCast> = {
    cast,
    validator,
    validating: Boolean(baseForm.validating),

    touch(field: string): void {
      markTouched(field);
      if (validateOn !== 'submit') {
        validateSingleField(field);
      }
    },

    touched(field?: string): boolean {
      return field === undefined ? touchedFields.size > 0 : touchedFields.has(field);
    },

    validate(field?: string): boolean {
      if (field !== undefined) {
        markTouched(field);
        return validateSingleField(field);
      }
      return validateAllFields();
    },

    invalid,

    valid(field: string): boolean {
      return touchedFields.has(field) && !invalid(field);
    },

    submit(
      methodOrRoute: HttpMethod | WayfinderRoute,
      urlOrOptions?: string | Record<string, unknown>,
      submitOptions?: Record<string, unknown>,
    ): boolean {
      if (!validateAllFields()) {
        return false;
      }

      if (typeof methodOrRoute === 'object') {
        baseForm.submit(
          methodOrRoute.method,
          methodOrRoute.url,
          typeof urlOrOptions === 'object' ? urlOrOptions : undefined,
        );
      } else if (typeof urlOrOptions === 'string') {
        baseForm.submit(methodOrRoute, urlOrOptions, submitOptions);
      }

      return true;
    },
  };

  return {
    ...baseForm,
    ...helpers,
  };
}
