<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Reconcilers;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CommerceCore\Enums\InvoiceStatus;
use Lalalili\CommerceCore\Enums\InvoiceType;
use Lalalili\CommerceCore\Models\OrderInvoice;
use Lalalili\CommerceCore\Services\OrderLifecycleService;
use Lalalili\CommercePayment\Contracts\InvoiceGateway;
use Lalalili\CommercePayment\Data\InvoiceResult;

/**
 * commerce-core 發票對帳 adapter：對 commerce-core 訂單依稅別分組開立發票並落 OrderInvoice。
 *
 * 需安裝 lalalili/commerce-core（composer suggest）。給 commerce-core 系 host（如 aitehub）；
 * 非 commerce-core host（如 cptw）以套件的 InvoiceGateway 自寫對帳。
 *
 * 對應綠界 ECPay 稅別：0→免稅(3)、1→應稅(1)、2→零稅率(2)。
 */
class CommerceCoreInvoiceSyncService
{
    /** @var array<int, int> */
    private array $ecpayTaxTypes = [0 => 3, 1 => 1, 2 => 2];

    public function __construct(
        private readonly InvoiceGateway $invoices,
        private readonly OrderLifecycleService $orders,
    ) {
    }

    /**
     * @return list<Model>
     */
    public function issueOrderInvoices(Model $order, ?int $updatedBy = null): array
    {
        $issued = [];

        foreach ($this->orders->detailsGroupedByTax($order) as $taxType => $items) {
            $invoiceItems = $this->invoiceItems($items);
            if ($invoiceItems === []) {
                continue;
            }

            $relateNumber = ((string) data_get($order, 'number')) . '-' . (int) $taxType;
            $result = $this->invoices->issue($this->context($order, (int) $taxType, $relateNumber, $invoiceItems));
            $issued[] = $this->recordInvoice($order, $relateNumber, $result, $invoiceItems, $updatedBy);
        }

        return $issued;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array{title: string, sales_price: int, qty: int}>
     */
    private function invoiceItems(array $items): array
    {
        return array_map(static fn (array $item): array => [
            'title'       => (string) ($item['title'] ?? ''),
            'sales_price' => (int) ($item['sales_price'] ?? 0),
            'qty'         => (int) ($item['qty'] ?? 0),
        ], $items);
    }

    /**
     * @param list<array{title: string, sales_price: int, qty: int}> $items
     * @return array<string, mixed>
     */
    private function context(Model $order, int $taxType, string $relateNumber, array $items): array
    {
        return [
            'relate_number'  => $relateNumber,
            'customer_email' => (string) data_get($order, 'user.email', ''),
            'tax_type'       => $this->ecpayTaxTypes[$taxType] ?? 1,
            'items'          => $items,
            'invoice_fields' => $this->invoiceFields($order, $taxType),
        ];
    }

    /**
     * 由 commerce-core InvoiceType 對應綠界載具/捐贈欄位。
     *
     * @return array<string, mixed>
     */
    private function invoiceFields(Model $order, int $taxType): array
    {
        $invoiceType = data_get($order, 'invoice_type');
        if ($invoiceType instanceof InvoiceType) {
            $invoiceType = $invoiceType->value;
        }
        /** @var array<string, mixed> $invoiceCode */
        $invoiceCode = is_array(data_get($order, 'invoice_code')) ? data_get($order, 'invoice_code') : [];

        $fields = $taxType === 2 ? ['ClearanceMark' => 2] : [];

        return array_merge($fields, match ((int) $invoiceType) {
            InvoiceType::Donation->value => [
                'Donation'    => 1,
                'LoveCode'    => (string) ($invoiceCode['code'] ?? ''),
                'CarrierType' => '',
            ],
            InvoiceType::DuplicateCertification->value => [
                'CarrierType' => 2,
                'CarrierNum'  => (string) ($invoiceCode['code'] ?? ''),
            ],
            InvoiceType::DuplicateMobile->value => [
                'CarrierType' => 3,
                'CarrierNum'  => (string) ($invoiceCode['code'] ?? ''),
            ],
            InvoiceType::Triplicate->value => [
                'CustomerIdentifier' => (string) ($invoiceCode['code'] ?? ''),
                'CustomerName'       => (string) ($invoiceCode['title'] ?? ''),
            ],
            default => [
                'CarrierType' => 1,
                'CarrierNum'  => '',
            ],
        });
    }

    /**
     * @param list<array{title: string, sales_price: int, qty: int}> $items
     */
    private function recordInvoice(Model $order, string $relateNumber, InvoiceResult $result, array $items, ?int $updatedBy): Model
    {
        /** @var class-string<Model> $invoiceModel */
        $invoiceModel = config('commerce.models.order_invoice', OrderInvoice::class);
        $salesAmount = array_sum(array_map(
            static fn (array $item): int => $item['sales_price'] * $item['qty'],
            $items,
        ));

        /** @var Model $invoice */
        $invoice = $invoiceModel::query()->updateOrCreate(
            ['order_number' => $relateNumber],
            [
                'user_id'           => data_get($order, 'user_id'),
                'order_id'          => $order->getKey(),
                'total_sales_price' => $salesAmount,
                'type'              => data_get($order, 'invoice_type'),
                'number'            => $result->invoiceNumber,
                'status'            => $result->successful ? InvoiceStatus::Complete : InvoiceStatus::Pending,
                'issued_at'         => $result->issuedAt,
                'created_by'        => $updatedBy ?? data_get($order, 'user_id'),
                'updated_by'        => $updatedBy ?? data_get($order, 'user_id'),
            ],
        );

        return $invoice;
    }
}
