<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rylacdang\Formcast\Facades\Formcast;
use Rylacdang\Formcast\FormcastServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FormcastServiceProvider::class,
        ];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Formcast' => Formcast::class,
        ];
    }
}
