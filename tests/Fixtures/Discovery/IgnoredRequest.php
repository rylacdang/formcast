<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Tests\Fixtures\Discovery;

use Illuminate\Foundation\Http\FormRequest;
use Rylacdang\Formcast\Attributes\FormcastIgnore;

#[FormcastIgnore]
final class IgnoredRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['secret' => ['required', 'string']];
    }
}
