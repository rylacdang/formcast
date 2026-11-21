<?php

declare(strict_types=1);

namespace Rylacdang\Formcast;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FormcastServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('formcast')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Formcast::class);
    }
}
