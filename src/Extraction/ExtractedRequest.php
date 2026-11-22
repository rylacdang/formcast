<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Extraction;

use Illuminate\Foundation\Http\FormRequest;

final readonly class ExtractedRequest
{
    /**
     * @param  class-string<FormRequest>  $class
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $attributes
     */
    public function __construct(
        public string $class,
        public string $exportName,
        public string $sourcePath,
        public array $rules,
        public array $messages = [],
        public array $attributes = [],
        public bool $hasPrepareForValidation = false,
        public bool $usedFormcastRules = false,
    ) {}
}
