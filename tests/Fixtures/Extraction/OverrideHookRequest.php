<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Tests\Fixtures\Extraction;

use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

final class OverrideHookRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        throw new RuntimeException('Should not be called when formcastRules exists.');
    }

    /**
     * @return array<string, mixed>
     */
    public function formcastRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}
