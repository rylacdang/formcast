# Custom Client-Side Rules

By default, custom `ValidationRule` objects and closures are treated as **server-only** (`serverFields`) so Formcast never guesses their behavior. When a custom rule can safely run on the client, you have two ways to expose it.

## Option 1: Implement `CastsToZod` on the Rule Class

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Rylacdang\Formcast\Contracts\CastsToZod;
use Rylacdang\Formcast\Emitters\ZodContext;

class PhilippineMobileNumber implements ValidationRule, CastsToZod
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^(09|\+639)\d{9}$/', $value)) {
            $fail('validation.ph_mobile')->translate();
        }
    }

    public function toZod(ZodContext $ctx): string
    {
        return $ctx->chain(
            'regex',
            '/^(09|\+639)\d{9}$/',
            $ctx->message('validation.ph_mobile'),
        );
    }
}
```

## Option 2: Register a Mapper via `Formcast::extend()`

In your `AppServiceProvider::boot()`:

```php
use Rylacdang\Formcast\Emitters\ZodContext;
use Rylacdang\Formcast\Facades\Formcast;

Formcast::extend('ph_mobile', function (ZodContext $ctx): void {
    $ctx->chain('regex', '/^(09|\+639)\d{9}$/', $ctx->message('validation.ph_mobile'));
});
```

You can also register invokable `RuleMapper` classes in `config/formcast.php` under `'mappers'`.
