<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Contracts;

use Rylacdang\Formcast\Emitters\ZodContext;

interface CastsToZod
{
    public function toZod(ZodContext $ctx): string;
}
