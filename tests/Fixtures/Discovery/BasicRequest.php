<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Tests\Fixtures\Discovery;

use Illuminate\Foundation\Http\FormRequest;

final class BasicRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['name' => ['required', 'string']];
    }
}
