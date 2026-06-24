<?php

declare(strict_types=1);

use Lalalili\CommerceCore\Enums\PaymentStatus;
use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Models\PaymentLog;
use Lalalili\CommerceCore\Models\Product;
use Lalalili\CommerceCore\Services\OrderLifecycleService;
use Lalalili\CommercePayment\Data\PaymentResult;
use Lalalili\CommercePayment\Enums\PaymentOutcome;
use Lalalili\CommercePayment\Exceptions\PaymentAmountMismatchException;
use Lalalili\CommercePayment\Reconcilers\CommerceCoreReconciler;

function createCommercePaymentOrder(string $number = '260624PAY', int $amount = 1000): Order
{
    $product = Product::query()->create([
        'title'       => 'Payment package course',
        'type'        => 1,
        'list_price'  => $amount,
        'sales_price' => $amount,
        'tax'         => 1,
    ]);

    /** @var Order $order */
    $order = app(OrderLifecycleService::class)->create(1, [
        ['product_id' => $product->id],
    ], [
        'number' => $number,
    ]);

    return $order;
}

it('delegates paid reconciliation to commerce-core payment application service', function (): void {
    $order = createCommercePaymentOrder('260624PAID', 1000);

    app(CommerceCoreReconciler::class)->reconcile(PaymentResult::paid(
        orderNumber: $order->number,
        statusCode: '1',
        statusMessage: '訂單成立已付款',
        payload: ['TradeStatus' => '1'],
        amount: 1000,
        paidAt: now(),
        gatewayLabel: '綠界',
    ));

    $order->refresh();

    expect($order->payment_status)->toBe(PaymentStatus::Complete)
        ->and(PaymentLog::query()->where('order_number', $order->number)->where('status_code', '1')->exists())->toBeTrue();
});

it('keeps strict amount mismatch behavior while recording the core payment log', function (): void {
    $order = createCommercePaymentOrder('260624MISS', 1000);

    try {
        app(CommerceCoreReconciler::class)->reconcile(PaymentResult::paid(
            orderNumber: $order->number,
            statusCode: '1',
            statusMessage: '訂單成立已付款',
            payload: ['TradeStatus' => '1'],
            amount: 999,
            paidAt: now(),
            gatewayLabel: '綠界',
        ));

        $this->fail('Expected payment amount mismatch exception was not thrown.');
    } catch (PaymentAmountMismatchException) {
        $order->refresh();

        expect($order->payment_status)->toBe(PaymentStatus::Pending)
            ->and(PaymentLog::query()->where('order_number', $order->number)->first()?->status_message)
            ->toBe('綠界付款金額999與訂單金額不符');
    }
});

it('maps refunded results through the commerce-core lifecycle', function (): void {
    $order = createCommercePaymentOrder('260624RFND', 1000);
    app(OrderLifecycleService::class)->markPaid($order->number, 'paid', now());

    app(CommerceCoreReconciler::class)->reconcile(new PaymentResult(
        orderNumber: $order->number,
        outcome: PaymentOutcome::Refunded,
        statusCode: '69',
        statusMessage: '退貨成功',
        payload: ['RC' => '69'],
        gatewayLabel: '玉山',
        outcomeMessage: '銀行已退款',
    ));

    $order->refresh();

    expect($order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($order->payment_status_message)->toBe('銀行已退款');
});
