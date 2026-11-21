# Formcast

**Cast your Laravel `FormRequest` rules to the client.**

Formcast reads your Laravel `FormRequest` classes and generates typed **Zod schemas**, **TypeScript types**, and **Laravel-identical error messages** for your Inertia (Vue / React) forms. It validates instantly in the browser and only calls the server (Precognition) for rules that need it, like `unique` and `exists`.

> [!NOTE]
> Formcast is currently under active development targeting `v0.1.0`.

## Packages

- **Composer:** `rylacdang/formcast`
- **npm:** `@rylacdang/formcast` (subpaths: `/vite`, `/vue`, `/react`)

## License

Formcast is open-sourced software licensed under the [MIT license](LICENSE.md).
