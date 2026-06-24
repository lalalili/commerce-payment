<?php

declare(strict_types=1);

use Lalalili\CommercePayment\Gateways\EcpayGateway;
use Lalalili\CommercePayment\Gateways\EsunGateway;
use Lalalili\CommercePayment\PaymentManager;

it('依 config 解析各支付方式 gateway', function (): void {
    $manager = app(PaymentManager::class);

    expect($manager->gateway('ecpay_credit'))->toBeInstanceOf(EcpayGateway::class)
        ->and($manager->gateway('ecpay_unionpay'))->toBeInstanceOf(EcpayGateway::class)
        ->and($manager->gateway('esun'))->toBeInstanceOf(EsunGateway::class);
});

it('同一 method 重複解析回同一實例', function (): void {
    $manager = app(PaymentManager::class);
    expect($manager->gateway('esun'))->toBe($manager->gateway('esun'));
});

it('未配置的 method 拋例外', function (): void {
    app(PaymentManager::class)->gateway('nope');
})->throws(InvalidArgumentException::class);

it('未知 driver 拋例外', function (): void {
    config()->set('commerce-payment.methods.weird', ['driver' => 'paypal']);
    app(PaymentManager::class)->gateway('weird');
})->throws(InvalidArgumentException::class);
