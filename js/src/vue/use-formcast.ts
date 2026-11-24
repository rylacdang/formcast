import { useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { createValidator, type Cast, type CastInput, type FormcastValidator } from '../index';

export type HttpMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

export type WayfinderRoute =
  | { readonly url: string; readonly method: HttpMethod }
  | { readonly action: string; readonly method: HttpMethod };

function resolveRouteUrl(route: WayfinderRoute): string {
  return 'url' in route ? route.url : route.action;
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

export interface InertiaFormBase {
  errors: Record<string, string | undefined>;
  hasErrors: boolean;
  processing: boolean;
  validating?: boolean;
  data(): Record<string, unknown>;
  setError(field: string, value: string): void;
  setError(errors: Record<string, string>): void;
  clearErrors(...fields: string[]): void;
  submit(method: HttpMethod, url: string, options?: Record<string, unknown>): void;
  submit(route: WayfinderRoute, options?: Record<string, unknown>): void;
  validate?(field: string): unknown;
  withPrecognition?(method: HttpMethod, url: string): InertiaFormBase;
}

export interface FormcastVueHelpers<TCast extends Cast> {
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

export type FormcastVueForm<
  TCast extends Cast,
  TData extends object = CastInput<TCast> & object,
> = TData & Omit<InertiaFormBase, 'submit' | 'validate'> & FormcastVueHelpers<TCast>;

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
): FormcastVueForm<TCast, TData> {
  const validateOn = options.validateOn ?? 'blur';
  const validator = createValidator(cast);
  const touchedFields = reactive(new Set<string>());

  const createInertiaForm = useForm as unknown as (data: object) => InertiaFormBase;
  let baseForm = createInertiaForm(initialData);

  const precognitionEndpoint = resolvePrecognitionEndpoint(cast, options.precognition);

  if (precognitionEndpoint !== null && typeof baseForm.withPrecognition === 'function') {
    baseForm = baseForm.withPrecognition(
      precognitionEndpoint.method,
      resolveRouteUrl(precognitionEndpoint),
    );
  }

  const originalSubmit = baseForm.submit.bind(baseForm);
  const precognitionValidate =
    typeof baseForm.validate === 'function' ? baseForm.validate.bind(baseForm) : null;

  const validateSingleField = (field: string): boolean => {
    const result = validator.validateField(field, baseForm.data());

    for (const cleared of result.cleared) {
      baseForm.clearErrors(cleared);
    }

    for (const [key, message] of Object.entries(result.errors)) {
      baseForm.setError(key, message);
    }

    if (result.needsServer && precognitionEndpoint !== null && precognitionValidate !== null) {
      precognitionValidate(field);
    }

    return result.valid;
  };

  const validateAllFields = (): boolean => {
    const result = validator.validateAll(baseForm.data());
    baseForm.clearErrors();

    if (!result.valid) {
      for (const [key, message] of Object.entries(result.errors)) {
        baseForm.setError(key, message);
      }
      return false;
    }

    return true;
  };

  const helpers: FormcastVueHelpers<TCast> = {
    cast,
    validator,
    get validating(): boolean {
      return Boolean(baseForm.validating);
    },

    touch(field: string): void {
      touchedFields.add(field);
      if (validateOn !== 'submit') {
        validateSingleField(field);
      }
    },

    touched(field?: string): boolean {
      return field === undefined ? touchedFields.size > 0 : touchedFields.has(field);
    },

    validate(field?: string): boolean {
      if (field !== undefined) {
        touchedFields.add(field);
        return validateSingleField(field);
      }
      return validateAllFields();
    },

    invalid(field: string): boolean {
      const error = baseForm.errors[field];
      return typeof error === 'string' && error.length > 0;
    },

    valid(field: string): boolean {
      return touchedFields.has(field) && !helpers.invalid(field);
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
        originalSubmit(
          methodOrRoute.method,
          resolveRouteUrl(methodOrRoute),
          typeof urlOrOptions === 'object' ? urlOrOptions : undefined,
        );
      } else if (typeof urlOrOptions === 'string') {
        originalSubmit(methodOrRoute, urlOrOptions, submitOptions);
      }

      return true;
    },
  };

  return new Proxy(baseForm, {
    get(target, prop, receiver): unknown {
      if (prop in helpers) {
        return Reflect.get(helpers, prop, helpers) as unknown;
      }
      return Reflect.get(target, prop, receiver) as unknown;
    },
    set(target, prop, value, receiver): boolean {
      return Reflect.set(target, prop, value, receiver);
    },
  }) as unknown as FormcastVueForm<TCast, TData>;
}
