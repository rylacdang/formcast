<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Rules;

enum RuleCategory: string
{
    case Client = 'client';
    case Server = 'server';
    case Meta = 'meta';
}
