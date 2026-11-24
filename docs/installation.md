# Installation & Configuration

## Requirements

- **PHP:** `^8.3`
- **Laravel:** `^12.0` or `^13.0`
- **Node.js:** `>=20`
- **Zod:** `^4.0`
- **Inertia (optional):** `@inertiajs/vue3` or `@inertiajs/react` `>=2.3` (2.3+ includes built-in Precognition)

## 1. Install the Packages

```bash
composer require rylacdang/formcast
npm install -D @rylacdang/formcast zod
```

## 2. Run the Installer

```bash
php artisan formcast:install
```

This publishes `config/formcast.php` and prints the Vite plugin and `tsconfig.json` path alias setup steps. You can also install the Formcast AI skill for non-Boost editors in the same command:

```bash
php artisan formcast:install --agents=antigravity,claude,cursor,copilot
```

## 3. Add the Vite Plugin

In `vite.config.ts`:

```ts
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { formcast } from '@rylacdang/formcast/vite';

export default defineConfig({
  plugins: [
    laravel({ input: ['resources/js/app.ts'], refresh: true }),
    vue(),
    formcast(),
  ],
});
```

### Vite Plugin Options

| Option | Default | Description |
| :--- | :--- | :--- |
| `command` | `'php artisan formcast:generate'` | Command run to regenerate casts |
| `patterns` | `['app/Http/Requests/**/*.php', 'app/Enums/**/*.php', 'lang/**/validation.php', 'resources/lang/**/validation.php']` | Watched file globs in dev mode |
| `debounce` | `150` | Debounce window (ms) |
| `runOnBuild` | `true` | Run generation on `vite build` |

Set `FORMCAST_SKIP=1` in your environment to bypass Vite regeneration (for example in Docker asset stages without PHP).

## 4. Configuration (`config/formcast.php`)

- `paths`: Directories or files scanned for concrete `FormRequest` classes (default: `[app_path('Http/Requests')]`).
- `output`: Output directory for generated TypeScript files (default: `resource_path('js/formcast')`).
- `discovery`: `'all'` (every concrete `FormRequest`) or `'attribute'` (only classes marked with `#[Formcast]`).
- `messages`: Enable/disable Laravel message resolution and configure default locale(s).
- `routes.enabled`: Scan registered routes to populate `usedBy` metadata.
- `unsupported`: `'server'` (default), `'warn'`, or `'fail'` when encountering unknown rules.
- `server_only`: Extra rule names to always treat as server-only.
- `redact`: Map of `FormRequest::class => ['field_name']` to exclude sensitive fields from client output.
- `mappers`: Map of `'rule_name' => CustomRuleMapper::class` for custom client-side mappers.
