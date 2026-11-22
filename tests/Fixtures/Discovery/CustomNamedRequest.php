<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Tests\Fixtures\Discovery;

use Illuminate\Foundation\Http\FormRequest;
use Rylacdang\Formcast\Attributes\Formcast;

#[Formcast(name: 'CustomOrder')]
final class CustomNamedRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['order_id' => ['required', 'integer']];
    }
}
