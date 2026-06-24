<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lalalili\CommerceCore\CommerceCoreServiceProvider;
use Lalalili\CommercePayment\CommercePaymentServiceProvider;
use Monolog\Handler\NullHandler;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CommerceCoreServiceProvider::class,
            CommercePaymentServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../vendor/lalalili/commerce-core/database/migrations');
        DB::statement('PRAGMA foreign_keys = ON');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // gateway 失敗分支寫入 host 的 slack_outside_api channel；testbench 無此 channel，以 null 提供。
        $app['config']->set('logging.channels.slack_outside_api', [
            'driver'  => 'monolog',
            'handler' => NullHandler::class,
        ]);
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->ensureSafeTestingDatabase();
    }

    protected function ensureSafeTestingDatabase(): void
    {
        $defaultConnection = (string) config('database.default');
        $testingDatabase = (string) config('database.connections.testing.database');

        if ($defaultConnection === 'testing' && $testingDatabase === ':memory:') {
            return;
        }

        throw new RuntimeException(
            "Unsafe package test database detected. Connection [{$defaultConnection}] with testing database [{$testingDatabase}] is not allowed."
        );
    }
}
