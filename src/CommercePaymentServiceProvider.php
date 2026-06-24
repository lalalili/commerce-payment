<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CommercePaymentServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('commerce-payment')
            ->hasConfigFile('commerce-payment');
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(PaymentManager::class);
    }
}
