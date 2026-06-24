<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Reconcilers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lalalili\CommerceCore\Models\PaymentLog;
use Lalalili\CommercePayment\Contracts\PaymentGateway;
use Lalalili\CommercePayment\Data\RefundResult;

/**
 * commerce-core 退款對帳 adapter：對 commerce-core 訂單呼叫渠道退款 API 並記 log。
 *
 * 需安裝 lalalili/commerce-core（composer suggest）。
 *
 * 設計：**退款只退款、不取消訂單**——取消訂單是 host 的動作，退款是否隨之發生由各專案以
 * `config('commerce-payment.refund.auto_on_cancel')` 自行決定（host 在取消流程中讀此旗標決定是否呼叫本服務）。
 * gateway 的 refund() 為純通訊、需 trade_no；本服務由 commerce-core 的 PaymentLog 解析後帶入。
 *
 * 註：綠界 DoAction（退款/請款/取消）僅適用信用卡；ATM/CVS/BARCODE 無退款 API，須後台人工
 * （見 ECPay-API-Skill guides 15 §30b）。host 應在呼叫前確認付款方式為信用卡。
 */
class CommerceCoreRefundSyncService
{
    public function __construct(private readonly PaymentGateway $payments)
    {
    }

    public function refund(Model $order): RefundResult
    {
        return DB::transaction(function () use ($order): RefundResult {
            $result = $this->payments->refund([
                'order_number' => (string) data_get($order, 'number'),
                'amount'       => (int) data_get($order, 'total_sales_price', 0),
                'trade_no'     => $this->resolveTradeNo($order),
            ]);

            $this->recordPaymentLog($result);

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
