<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Tests\Fixtures\Extraction;

use Illuminate\Foundation\Http\FormRequest;
use Rylacdang\Formcast\Formcast;

final class PrepareAndInjectedRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => strtolower((string) $this->input('slug'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(Formcast $registry): array
    {
        return [
            'slug' => ['required', 'string'],
            'has_registry' => [$registry instanceof Formcast ? 'boolean' : 'string'],
        ];
    }
}
