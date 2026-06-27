<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Data;

/**
 * 定期定額管理動作（取消 / 重新授權）的正規化結果（純通訊，不含訂單欄位）。
 *
 * @see https://developers.ecpay.com.tw 之 CreditCardPeriodAction
 */
final readonly class RecurringActionResult
{
    /**
     * @param array<string, mixed> $payload 原始回應內容
     */
    public function __construct(
        public string $orderNumber,
        public bool $success,
        public string $statusCode,
        public string $statusMessage,
        public array $payload = [],
    ) {
    }
}
