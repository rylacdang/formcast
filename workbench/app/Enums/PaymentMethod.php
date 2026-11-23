<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Gcash = 'gcash';
    case Card = 'card';
}
