<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Lalalili\CommercePayment\Data\PaymentResult;

/**
 * 取得正規化付款結果後派發；host 可改用 listener 對帳（PaymentReconciler 的事件式替代縫）。
 */
class PaymentResultReceived
{
    use Dispatchable;

    public function __construct(
        public string $method,
        public PaymentResult $result,
    ) {
    }
}
