<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Data;

/**
 * 退款 / 退刷 / 取消授權的正規化結果（純通訊，不含訂單欄位）。
 */
final readonly class RefundResult
{
    /**
     * @param array<string, mixed> $payload 原始回應內容
     */
    public function __construct(
        public string $orderNumber,
        public bool $refunded,
        public string $statusCode,
        public string $statusMessage,
        public array $payload = [],
    ) {
    }
}
