# AI Skill & Laravel Boost

Formcast ships with first-class AI guidelines and a dedicated usage skill so AI coding assistants always follow your project's Formcast conventions (editing rules only in PHP `FormRequest` classes, never hand-editing generated TypeScript, and using `useFormcast()` instead of raw `useForm()`).

## Laravel Boost (Automatic)

`rylacdang/formcast` includes `resources/boost/guidelines/core.blade.php` and `resources/boost/skills/formcast/SKILL.md`. If your application uses **Laravel Boost**, running `php artisan boost:install` discovers them automatically—no manual copying required.

## Other AI Tools (`formcast:install --agents=...`)

Install the Formcast skill for Antigravity, Claude Code, Cursor, or GitHub Copilot in one command:

```bash
php artisan formcast:install --agents=antigravity,claude,cursor,copilot
```

| Agent | Installed Destination |
| :--- | :--- |
| Antigravity | `.agents/skills/formcast/SKILL.md` (+ `references/*`) |
| Claude Code | `.claude/skills/formcast/SKILL.md` (+ `references/*`) |
| Cursor | `.cursor/rules/formcast.mdc` |
| GitHub Copilot | `.github/instructions/formcast.instructions.md` |
| Laravel Boost | Auto-discovered from `vendor/rylacdang/formcast/resources/boost/` |

Pass `--force` to overwrite previously installed skill files when upgrading Formcast.
