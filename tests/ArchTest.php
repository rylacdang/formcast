<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;

arch('strict types')
    ->expect('Rylacdang\Formcast')
    ->toUseStrictTypes();

arch('no debug helpers')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('contracts are interfaces')
    ->expect('Rylacdang\Formcast\Contracts')
    ->toBeInterfaces();

arch('facades extend laravel facade')
    ->expect('Rylacdang\Formcast\Facades')
    ->toExtend(Facade::class);
