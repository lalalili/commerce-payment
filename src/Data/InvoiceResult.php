<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Data;

use Illuminate\Support\Carbon;

/**
 * 綠界 B2C 發票操作（開立/作廢/查詢）的正規化結果（純通訊，不含訂單欄位）。
 */
final readonly class InvoiceResult
{
    /**
     * @param array<string, mixed> $payload 原始回應內容
     */
    public function __construct(
        public string $relateNumber,
        public bool $successful,
        public string $statusCode,
        public string $statusMessage,
        public ?string $invoiceNumber = null,
        public ?Carbon $issuedAt = null,
        public array $payload = [],
    ) {
    }
}
