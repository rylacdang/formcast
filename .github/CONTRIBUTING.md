# Contributing to Formcast

Thank you for considering contributing to Formcast!

## Repository Layout

This repository is a monorepo:

- **Root (`src/`, `config/`, `resources/`, `tests/`):** Composer package `rylacdang/formcast`
- **`js/`:** npm package `@rylacdang/formcast` (subpaths `.`, `./vite`, `./vue`, `./react`)

## Local Setup

```bash
composer install
npm --prefix js install
```

## Running Checks

### PHP

```bash
composer test          # Pest test suite
composer analyse       # Larastan (level 8)
composer format        # Laravel Pint (auto-fix)
composer format:check  # Laravel Pint (check only)
```

### JavaScript / TypeScript

```bash
cd js
npm test               # Vitest
npm run typecheck      # tsc --noEmit
npm run lint           # ESLint
npm run format:check   # Prettier
npm run build          # tsup
```

### Parity Suite

```bash
composer test -- --group=parity
cd js && npm run test:parity
```

## Invariants

1. **Deterministic output:** Generated files must have sorted keys and stable formatting.
2. **Never approximate:** Any rule that cannot be safely mapped to Zod stays server-only.
3. **Rule mappers require parity:** Every new client rule mapper must include a PHP mapper, snapshot test, JS helper (if needed), and Laravel-vs-Zod parity payloads.
4. **Framework-agnostic core:** The core JS entry (`js/src/index.ts`) must never import `vue`, `react`, or `@inertiajs/*`.
5. **Inertia error bag shape:** Client error bags use dot-notation keys and a single string per field.

## Commits

Use [Conventional Commits](https://www.conventionalcommits.org/):

```text
<type>(<scope>): <short imperative subject>

- detailed bullet explaining what changed
```
