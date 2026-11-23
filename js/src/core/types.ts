import { type z } from 'zod';

export interface RouteUsage {
  readonly action: string;
  readonly methods: readonly string[];
  readonly uri: string;
  readonly name?: string;
}

export interface CastDefinition<TSchema extends z.ZodType = z.ZodType> {
  readonly name: string;
  readonly schema: TSchema;
  readonly serverFields: readonly string[];
  readonly dependencies: Readonly<Record<string, readonly string[]>>;
  readonly usedBy: readonly RouteUsage[];
}

export type Cast<TSchema extends z.ZodType = z.ZodType> = CastDefinition<TSchema>;

export type CastInput<TCast extends Cast> = z.input<TCast['schema']>;
export type CastOutput<TCast extends Cast> = z.output<TCast['schema']>;

export type ErrorBag = Record<string, string>;

export interface FileLike {
  readonly name?: string;
  readonly size: number;
  readonly type?: string;
}
