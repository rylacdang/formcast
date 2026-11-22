<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Contracts;

use Rylacdang\Formcast\Emitters\ZodContext;
use Rylacdang\Formcast\Rules\NormalizedRule;

interface RuleMapper
{
    public function map(NormalizedRule $rule, ZodContext $ctx): void;
}
