<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Tests\Fixtures\Extraction;

use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

final class FailingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        throw new RuntimeException('Missing tenant context');
    }
}
