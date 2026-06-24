<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Data;

use Illuminate\Support\Carbon;
use Lalalili\CommercePayment\Enums\PaymentOutcome;

/**
 * 金流閘道（gateway）通訊後產生的正規化付款結果。
 *
 * gateway 只負責「呼叫 API + 驗章 + 把原始回應對應成此 DTO」（純通訊，不寫訂單）；
 * host 綁定的 PaymentReconciler 依此 DTO 做所有訂單對帳。
 */
final readonly class PaymentResult
{
    /**
     * @param array<string, mixed> $payload 寫入金流 log 的原始回應內容
     * @param ?string $outcomeMessage 訂單狀態更新／markPaid 用的訊息；null 時沿用 statusMessage
     *                                （部分 gateway 的「狀態 log 訊息」與「對帳結果訊息」來源不同，如 ESun 付款成功）
     */
    public function __construct(
        public string $orderNumber,
        public PaymentOutcome $outcome,
        public string $statusCode,
        public string $statusMessage,
        public array $payload = [],
        public ?int $amount = null,
        public ?Carbon $paidAt = null,
        public ?string $tradeNo = null,
        public string $gatewayLabel = '',
        public ?string $outcomeMessage = null,
    ) {
    }

    public function isPaid(): bool
    {
        return $this->outcome === PaymentOutcome::Paid;
    }

    /**
     * 訂單狀態更新 / markPaid 使用的訊息（預設與狀態 log 訊息相同）。
     */
    public function orderMessage(): string
    {
        return $this->outcomeMessage ?? $this->statusMessage;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function paid(
        string $orderNumber,
        string $statusCode,
        string $statusMessage,
        array $payload,
        int $amount,
        Carbon $paidAt,
        string $gatewayLabel,
        ?string $tradeNo = null,
        ?string $outcomeMessage = null,
    ): self {
        return new self(
            orderNumber: $orderNumber,
            outcome: PaymentOutcome::Paid,
            statusCode: $statusCode,
            statusMessage: $statusMessage,
            payload: $payload,
            amount: $amount,
            paidAt: $paidAt,
            tradeNo: $tradeNo,
            gatewayLabel: $gatewayLabel,
            outcomeMessage: $outcomeMessage,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function pending(string $orderNumber, string $statusCode, string $statusMessage, array $payload, ?string $tradeNo = null): self
    {
        return new self(
            orderNumber: $orderNumber,
            outcome: PaymentOutcome::Pending,
            statusCode: $statusCode,
            statusMessage: $statusMessage,
            payload: $payload,
            tradeNo: $tradeNo,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function queryFailed(string $orderNumber, string $statusCode, array $payload): self
    {
        return new self(
            orderNumber: $orderNumber,
            outcome: PaymentOutcome::QueryFailed,
            statusCode: $statusCode,
            statusMessage: '付款狀態查詢失敗',
            payload: $payload,
        );
    }
}
