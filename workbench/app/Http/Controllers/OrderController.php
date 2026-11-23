<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Workbench\App\Http\Requests\StoreOrderRequest;

final class OrderController
{
    public function store(StoreOrderRequest $request): void {}
}
