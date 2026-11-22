<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Tests\Fixtures\Discovery\Nested;

use Illuminate\Foundation\Http\FormRequest;

final class SubdirRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['title' => ['required', 'string']];
    }
}
