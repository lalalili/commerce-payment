<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Reconcilers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lalalili\CommerceCore\Models\PaymentLog;
use Lalalili\CommerceCore\Services\OrderLifecycleService;
use Lalalili\CommercePayment\Contracts\PaymentReconciler;
use Lalalili\CommercePayment\Data\PaymentResult;
use Lalalili\CommercePayment\Exceptions\PaymentAmountMismatchException;

/**
 * 對帳 adapter：把正規化 PaymentResult 套到 lalalili/commerce-core 的訂單。
 *
 * 需安裝 lalalili/commerce-core（composer suggest）。非 commerce-core host 請自行綁定
 * 一個包住自家訂單服務的 PaymentReconciler。
 */
class CommerceCoreReconciler implements PaymentReconciler
{
    public function __construct(private readonly OrderLifecycleService $orders)
    {
    }

    public function reconcile(PaymentResult $result, ?int $updatedBy = null): void
    {
        /** @var class-string<Model> $orderModel */
        $orderModel = config('commerce.models.order');

        DB::transaction(function () use ($result, $updatedBy, $orderModel): void {
            $this->recordPaymentLog($result);

            $order = $orderModel::query()
                ->where('number', $result->orderNumber)
                ->lockForUpdate()
                ->first();

            if (! $order instanceof Model || ! $result->isPaid()) {
                return;
            }

            $this->ensureAmountMatches($order, $result);

            $this->orders->markPaid(
                orderNumber: $result->orderNumber,
                paymentStatusMessage: $result->orderMessage(),
                paymentTime: $result->paidAt ?? now(),
                updatedBy: $updatedBy,
            );
        });
    }

    private function recordPaymentLog(PaymentResult $result): void
    {
        /** @var class-string<PaymentLog> $paymentLogModel */
        $paymentLogModel = config('commerce.models.payment_log', PaymentLog::class);

        $paymentLogModel::query()->updateOrCreate(
            ['order_number' => $result->orderNumber, 'status_code' => $result->statusCode],
            ['response' => $result->payload, 'status_message' => $result->statusMessage],
        );
    }

    private function ensureAmountMatches(Model $order, PaymentResult $result): void
    {
        if (! (bool) config('commerce-payment.reconcile.strict_amount_check', true)) {
            return;
        }

        if ($result->amount === null) {
            return;
        }

        $expected = (int) data_get($order, 'total_sales_price', 0);
        if ($expected === $result->amount) {
            return;
        }

        throw PaymentAmountMismatchException::forOrder($result->orderNumber, $expected, $result->amount);
    }
}
