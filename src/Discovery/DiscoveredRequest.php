<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Discovery;

use Illuminate\Foundation\Http\FormRequest;

final readonly class DiscoveredRequest
{
    /**
     * @param  class-string<FormRequest>  $class
     */
    public function __construct(
        public string $class,
        public string $filePath,
        public string $exportName,
    ) {}
}
