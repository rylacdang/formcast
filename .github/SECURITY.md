# Security Policy

## Supported Versions

| Version | Supported |
| :--- | :--- |
| `0.x` (latest) | Yes |

## Reporting a Vulnerability

Please **do not** report security vulnerabilities through public GitHub issues.

Instead, use [GitHub Private Vulnerability Reporting](https://github.com/rylacdang/formcast/security/advisories/new) on this repository. You can expect an initial response within 72 hours.

## Important Note on Client-Side Validation

Formcast generates client-side Zod schemas to improve user experience. **Client-side validation is never a security boundary.** Laravel's server-side validation on every request remains mandatory and authoritative.

Also note that generated TypeScript files are bundled and shipped to the browser—avoid placing sensitive secrets or internal-only values in client-emitted rules, or exclude sensitive fields using the `redact` option in `config/formcast.php`.
