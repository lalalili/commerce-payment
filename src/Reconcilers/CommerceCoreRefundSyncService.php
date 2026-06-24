<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Reconcilers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lalalili\CommerceCore\Models\PaymentLog;
use Lalalili\CommerceCore\Services\OrderLifecycleService;
use Lalalili\CommercePayment\Contracts\PaymentGateway;
use Lalalili\CommercePayment\Data\RefundResult;

/**
 * commerce-core 退款對帳 adapter：對 commerce-core 訂單退款（退刷/取消授權）並記 log、取消訂單。
 *
 * 需安裝 lalalili/commerce-core（composer suggest）。給 commerce-core 系 host（如 aitehub）；
 * 非 commerce-core host 以套件 gateway 的 refund() 自寫對帳。
 *
 * gateway 的 refund() 為純通訊、需傳入 trade_no；本服務由 commerce-core 的 PaymentLog 解析後帶入。
 */
class CommerceCoreRefundSyncService
{
    public function __construct(
        private readonly PaymentGateway $payments,
        private readonly OrderLifecycleService $orders,
    ) {
    }

    public function refund(Model $order, ?int $updatedBy = null): RefundResult
    {
        return DB::transaction(function () use ($order, $updatedBy): RefundResult {
            $result = $this->payments->refund([
                'order_number' => (string) data_get($order, 'number'),
                'amount'       => (int) data_get($order, 'total_sales_price', 0),
                'trade_no'     => $this->resolveTradeNo($order),
            ]);

            $this->recordPaymentLog($result);

            if ($result->refunded) {
                $this->orders->cancel($result->orderNumber, $updatedBy);
            }

            return $result;
        });
    }

    private function resolveTradeNo(Model $order): string
    {
        $order->loadMissing('paymentLogs');

        foreach ($order->getRelationValue('paymentLogs') ?? [] as $paymentLog) {
            $response = data_get($paymentLog, 'response');
            if (is_array($response) && is_string($response['TradeNo'] ?? null)) {
                return $response['TradeNo'];
            }
        }

        return '';
    }

    private function recordPaymentLog(RefundResult $result): void
    {
        /** @var class-string<PaymentLog> $paymentLogModel */
        $paymentLogModel = config('commerce.models.payment_log', PaymentLog::class);

        $paymentLogModel::query()->updateOrCreate(
            ['order_number' => $result->orderNumber, 'status_code' => "refund:{$result->statusCode}"],
            ['response' => $result->payload, 'status_message' => $result->statusMessage],
        );
    }
}
