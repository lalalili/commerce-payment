<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Tests;

use Lalalili\CommercePayment\CommercePaymentServiceProvider;
use Monolog\Handler\NullHandler;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CommercePaymentServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // gateway 失敗分支寫入 host 的 slack_outside_api channel；testbench 無此 channel，以 null 提供。
        $app['config']->set('logging.channels.slack_outside_api', [
            'driver'  => 'monolog',
            'handler' => NullHandler::class,
        ]);
    }
}
