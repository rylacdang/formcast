# Changelog

All notable changes to `formcast` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.1] - 2026-11-25

### Added
- Support reflecting closure routes (`ReflectionFunction`) in `RouteScanner`.
- Emit Wayfinder-style named-route barrel exports (`routes/<group>/index.ts`) in `formcast:generate`.
- Support Wayfinder `.form()` `{ action, method }` objects alongside `{ url, method }` in Vue and React `useFormcast()`.
- Include `README.md` (with absolute logo and docs links) and `LICENSE.md` in published npm tarballs.

## [0.1.0] - 2026-11-25

### Added
- **PHP Generator (`rylacdang/formcast`):**
  - `RequestDiscoverer` with `all` and `attribute` discovery modes, `#[Formcast]` custom export names, and `#[FormcastIgnore]` exclusions.
  - Runtime `FormRequestExtractor` with `GenerationContext`, `formcastRules()` override hook, and `prepareForValidation()` detection.
  - `RuleNormalizer`, `RuleTreeBuilder`, `RuleClassifier`, `TypeInferrer` (Laravel `getSize()` precedence), and safe PCRE-to-JS `RegexTranslator`.
  - `MessageResolver` producing Laravel-identical validation messages, type-specific size variants, custom `messages()` / `attributes()`, and deferred runtime placeholders.
  - `ZodEmitter` with mappers for presence, type, size, format, set/enum, date, file, password, and cross-field `superRefine` rules, plus `CastsToZod` and `Formcast::extend()` extensibility.
  - `EnumEmitter`, `TypeEmitter`, `ManifestEmitter`, `RouteScanner` (`usedBy`), deterministic `TsCode` builder, and content-hashed `FileWriter` with stale cleanup and `--check` drift detection.
  - `formcast:generate` and `formcast:install` Artisan commands.
  - Laravel Boost guidelines and `formcast` AI skill (`resources/boost/`) with `--agents=antigravity,claude,cursor,copilot` installer support.
- **TypeScript Runtime (`@rylacdang/formcast`):**
  - Framework-agnostic core (`.`) with `defineCast`, Laravel-semantics `fc.*` helpers, cross-field helpers, wildcard path utilities, dependency graph, `toErrorBag`, and `createValidator`.
  - Debounced Vite plugin (`./vite`) watching FormRequests, Enums, and `validation.php` lang files with dev overlay error reporting.
  - First-class Inertia `useFormcast()` composable for Vue (`./vue`) and hook for React (`./react`) with Wayfinder route support and gated Precognition for `serverFields`.
- **Verification & Docs:**
  - Automated Laravel-vs-Zod parity test harness and CI workflow proving identical pass/fail and error messages.
  - Full documentation suite under `docs/` and tag-driven `release.yml` workflow.
