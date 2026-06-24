<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment;

use InvalidArgumentException;
use Lalalili\CommercePayment\Contracts\PaymentGateway;
use Lalalili\CommercePayment\Contracts\PaymentReconciler;
use Lalalili\CommercePayment\Data\PaymentResult;
use Lalalili\CommercePayment\Gateways\EcpayGateway;
use Lalalili\CommercePayment\Gateways\EsunGateway;

/**
 * 依 config('commerce-payment.methods.<key>') 解析支付方式 gateway，並把對帳轉交 host 綁定的 reconciler。
 *
 * 「可配置多支付方式」的進入點：每個 method = driver(ecpay/esun) + 該方式的設定。
 */
class PaymentManager
{
    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    public function gateway(string $method): PaymentGateway
    {
        if (isset($this->resolved[$method])) {
            return $this->resolved[$method];
        }

        $config = config("commerce-payment.methods.$method");
        if (! is_array($config)) {
            throw new InvalidArgumentException("Payment method [{$method}] is not configured.");
        }

        $driver = (string) ($config['driver'] ?? '');

        return $this->resolved[$method] = match ($driver) {
            'ecpay' => new EcpayGateway($config),
            'esun'  => new EsunGateway($config),
            default => throw new InvalidArgumentException("Unsupported payment driver [{$driver}] for method [{$method}]."),
        };
    }

    /**
     * 把正規化結果交給 host 綁定的對帳器（host 須 bind PaymentReconciler 實作）。
     */
    public function reconcile(PaymentResult $result, ?int $updatedBy = null): void
    {
        app(PaymentReconciler::class)->reconcile($result, $updatedBy);
    }
}
