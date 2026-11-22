<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Tests\Fixtures\Extraction;

use Illuminate\Foundation\Http\FormRequest;

final class ContextDependentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var object{id: int}|null $user */
        $user = $this->user();
        $orderId = $this->route('order');

        return [
            'email' => ['required', 'email', 'unique:users,email,'.($user?->id ?? 'NULL')],
            'reference' => ['nullable', 'string', 'max:'.($orderId !== null ? 50 : 25)],
        ];
    }
}
