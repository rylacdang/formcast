<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Tests\Fixtures\Discovery;

use Illuminate\Foundation\Http\FormRequest;
use Rylacdang\Formcast\Attributes\Formcast;

#[Formcast]
final class AttributedRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['email' => ['required', 'email']];
    }
}
