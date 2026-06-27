<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Data;

use Illuminate\Support\Carbon;
use Lalalili\CommercePayment\Enums\PaymentOutcome;

/**
 * 綠界信用卡定期定額（Credit Period）單期授權的正規化結果。
 *
 * 第 1 期由建單的 ReturnURL 回傳（標準欄位）；第 2 期起由 PeriodReturnURL 回傳，
 * 多帶 PeriodType / Frequency / ExecTimes / TotalSuccessTimes / gwsr。
 * 所有期數通知都帶同一個原始 MerchantTradeNo，以 totalSuccessTimes 區分第幾期（冪等鍵）。
 */
final readonly class RecurringPaymentResult
{
    /**
     * @param array<string, mixed> $payload 寫入金流 log 的原始回應內容
     */
    public function __construct(
        public string $orderNumber,
        public PaymentOutcome $outcome,
        public string $statusCode,
        public string $statusMessage,
        public array $payload = [],
        public ?int $amount = null,
        public ?Carbon $paidAt = null,
        public ?string $gwsr = null,
        public ?string $periodType = null,
        public ?int $frequency = null,
        public ?int $execTimes = null,
        public int $totalSuccessTimes = 0,
        public string $gatewayLabel = '',
    ) {
    }

    public function isPaid(): bool
    {
        return $this->outcome === PaymentOutcome::Paid;
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
        int $totalSuccessTimes,
        string $gatewayLabel,
        ?string $gwsr = null,
        ?string $periodType = null,
        ?int $frequency = null,
        ?int $execTimes = null,
    ): self {
        return new self(
            orderNumber: $orderNumber,
            outcome: PaymentOutcome::Paid,
            statusCode: $statusCode,
            statusMessage: $statusMessage,
            payload: $payload,
            amount: $amount,
            paidAt: $paidAt,
            gwsr: $gwsr,
            periodType: $periodType,
            frequency: $frequency,
            execTimes: $execTimes,
            totalSuccessTimes: $totalSuccessTimes,
            gatewayLabel: $gatewayLabel,
        );
    }

    /**
     * 單期授權失敗（綠界連續失敗 6 次會自動終止合約）。
     *
     * @param array<string, mixed> $payload
     */
    public static function failed(string $orderNumber, string $statusCode, string $statusMessage, array $payload): self
    {
        return new self(
            orderNumber: $orderNumber,
            outcome: PaymentOutcome::Declined,
            statusCode: $statusCode,
            statusMessage: $statusMessage,
            payload: $payload,
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
            statusMessage: '定期定額狀態查詢失敗',
            payload: $payload,
        );
    }
}
