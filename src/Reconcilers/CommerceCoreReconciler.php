<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Reconcilers;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CommerceCore\DTOs\PaymentApplicationData;
use Lalalili\CommerceCore\Enums\PaymentApplicationOutcome;
use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Services\PaymentApplicationService;
use Lalalili\CommerceCore\Support\ModelAttributeMapper;
use Lalalili\CommercePayment\Contracts\PaymentReconciler;
use Lalalili\CommercePayment\Data\PaymentResult;
use Lalalili\CommercePayment\Enums\PaymentOutcome;
use Lalalili\CommercePayment\Exceptions\PaymentAmountMismatchException;

/**
 * 對帳 adapter：把正規化 PaymentResult 套到 lalalili/commerce-core 的訂單。
 *
 * 需安裝 lalalili/commerce-core（composer suggest）。非 commerce-core host 請自行綁定
 * 一個包住自家訂單服務的 PaymentReconciler。
 */
class CommerceCoreReconciler implements PaymentReconciler
{
    public function __construct(
        private readonly PaymentApplicationService $payments,
        private readonly ModelAttributeMapper $attributes,
    ) {
    }

    public function reconcile(PaymentResult $result, ?int $updatedBy = null): void
    {
        $this->payments->apply($this->toPaymentApplicationData($result), $updatedBy);
        $this->throwIfStrictAmountMismatch($result);
    }

    private function toPaymentApplicationData(PaymentResult $result): PaymentApplicationData
    {
        return new PaymentApplicationData(
            orderNumber: $result->orderNumber,
            outcome: $this->toPaymentApplicationOutcome($result->outcome),
            payload: $result->payload,
            statusCode: $result->statusCode,
            statusMessage: $result->statusMessage,
            amount: $result->amount,
            paidAt: $result->paidAt ?? ($result->isPaid() ? now() : null),
            outcomeMessage: $result->orderMessage(),
            gatewayLabel: $result->gatewayLabel,
        );
    }

    private function toPaymentApplicationOutcome(PaymentOutcome $outcome): PaymentApplicationOutcome
    {
        return match ($outcome) {
            PaymentOutcome::Paid          => PaymentApplicationOutcome::Paid,
            PaymentOutcome::Pending       => PaymentApplicationOutcome::Pending,
            PaymentOutcome::Declined      => PaymentApplicationOutcome::Declined,
            PaymentOutcome::Refunded      => PaymentApplicationOutcome::Refunded,
            PaymentOutcome::UserCancelled => PaymentApplicationOutcome::UserCancelled,
            PaymentOutcome::QueryFailed   => PaymentApplicationOutcome::QueryFailed,
        };
    }

    private function throwIfStrictAmountMismatch(PaymentResult $result): void
    {
        if (! $result->isPaid()) {
            return;
        }

        if (! (bool) config('commerce-payment.reconcile.strict_amount_check', true)) {
            return;
        }

        if ($result->amount === null) {
            return;
        }

        $order = $this->findOrder($result->orderNumber);

        if (! $order instanceof Model) {
            return;
        }

        $expected = (int) $this->attributes->value($order, 'orders', 'total_sales_price', 0);
        if ($expected === $result->amount) {
            return;
        }

        throw PaymentAmountMismatchException::forOrder($result->orderNumber, $expected, $result->amount);
    }

    private function findOrder(string $orderNumber): ?Model
    {
        /** @var class-string<Model> $orderModel */
        $orderModel = $this->orderModel();

        /** @var Model|null $order */
        $order = $orderModel::query()
            ->where($this->attributes->column('orders', 'number', 'number') ?? 'number', $orderNumber)
            ->first();

        return $order;
    }

    /**
     * @return class-string<Model>
     */
    private function orderModel(): string
    {
        $model = config('commerce.models.order', Order::class);

        return is_string($model) && is_a($model, Model::class, true) ? $model : Order::class;
    }
}
