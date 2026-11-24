<?php

declare(strict_types=1);

namespace Rylacdang\Formcast;

use Rylacdang\Formcast\Commands\GenerateCommand;
use Rylacdang\Formcast\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FormcastServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('formcast')
            ->hasConfigFile()
            ->hasCommands(
                GenerateCommand::class,
                InstallCommand::class,
            );
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Formcast::class);
    }
}
