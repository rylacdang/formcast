<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Tests\Fixtures\Extraction;

use Illuminate\Foundation\Http\FormRequest;

final class StandardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'age' => ['nullable', 'integer', 'min:18'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'We need your email address.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'email address',
        ];
    }
}
