<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Gateways;

use Ecpay\Sdk\Factories\Factory;
use Illuminate\Support\Carbon;
use Lalalili\CommercePayment\Contracts\InvoiceGateway;
use Lalalili\CommercePayment\Data\InvoiceResult;
use Lalalili\CommercePayment\Support\EcpayEndpointResolver;
use Throwable;

/**
 * 綠界 B2C 電子發票閘道（AES-JSON 協議）。純通訊，設定由 config 注入。
 *
 * host-agnostic：發票類型→載具/捐贈對應由 host 解析後以 invoice_fields 傳入；
 * 本 gateway 只組協議封包 + items + SalesAmount，並解析回應為 InvoiceResult。
 */
class EcpayInvoiceGateway implements InvoiceGateway
{
    private string $merchantId;

    private EcpayEndpointResolver $endpoints;

    private Factory $factory;

    /**
     * @param array<string, mixed> $config merchant_id、hash_key、hash_iv、stage_merchant_ids
     */
    public function __construct(array $config, ?Factory $factory = null)
    {
        $this->merchantId = (string) ($config['merchant_id'] ?? '');
        /** @var array<int, string> $stage */
        $stage = $config['stage_merchant_ids'] ?? ['2000132', '3085340'];
        $this->endpoints = new EcpayEndpointResolver($this->merchantId, $stage);
        $this->factory = $factory ?? new Factory([
            'hashKey' => (string) ($config['hash_key'] ?? ''),
            'hashIv'  => (string) ($config['hash_iv'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function issue(array $context): InvoiceResult
    {
        $relateNumber = (string) ($context['relate_number'] ?? '');
        /** @var array<int, array{title?: string, qty?: int, sales_price?: int}> $items */
        $items = is_array($context['items'] ?? null) ? $context['items'] : [];
        $itemWord = (string) ($context['item_word'] ?? '式');
        $taxType = (int) ($context['tax_type'] ?? 1);

        $salesAmount = array_sum(array_map(
            static fn (array $item): int => (int) ($item['sales_price'] ?? 0) * (int) ($item['qty'] ?? 0),
            $items,
        ));

        $data = array_merge([
            'MerchantID'    => $this->merchantId,
            'RelateNumber'  => $relateNumber,
            'CustomerEmail' => (string) ($context['customer_email'] ?? ''),
            'Print'         => '0',
            'TaxType'       => $taxType,
            'InvType'       => '07',
            'SalesAmount'   => $salesAmount,
            'Items'         => array_map(static fn (array $item): array => [
                'ItemName'    => (string) ($item['title'] ?? ''),
                'ItemCount'   => (int) ($item['qty'] ?? 0),
                'ItemWord'    => $itemWord,
                'ItemPrice'   => (int) ($item['sales_price'] ?? 0),
                'ItemTaxType' => $taxType,
                'ItemAmount'  => (int) ($item['sales_price'] ?? 0) * (int) ($item['qty'] ?? 0),
            ], $items),
        ], is_array($context['invoice_fields'] ?? null) ? $context['invoice_fields'] : []);

        $payload = $this->post('/B2CInvoice/Issue', $data);

        return $this->resultFromPayload($relateNumber, $payload);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function void(array $context): InvoiceResult
    {
        $invoiceNumber = (string) ($context['invoice_number'] ?? '');
        $payload = $this->post('/B2CInvoice/Invalid', [
            'MerchantID' => $this->merchantId,
            'InvoiceNo'  => $invoiceNumber,
            'Reason'     => (string) ($context['reason'] ?? '訂單取消'),
        ]);

        $result = $this->resultFromPayload((string) ($context['relate_number'] ?? ''), $payload);

        return new InvoiceResult(
            relateNumber: $result->relateNumber,
            successful: $result->successful,
            statusCode: $result->statusCode,
            statusMessage: $result->statusMessage,
            invoiceNumber: $invoiceNumber,
            issuedAt: $result->issuedAt,
            payload: $result->payload,
        );
    }

    public function query(string $relateNumber): InvoiceResult
    {
        $payload = $this->post('/B2CInvoice/GetIssue', [
            'MerchantID'   => $this->merchantId,
            'RelateNumber' => $relateNumber,
        ]);

        return $this->resultFromPayload($relateNumber, $payload);
    }

    public function checkLoveCode(string $loveCode): bool
    {
        $data = $this->responseData($this->post('/B2CInvoice/CheckLoveCode', [
            'MerchantID' => $this->merchantId,
            'LoveCode'   => $loveCode,
        ]));

        return (int) ($data['RtnCode'] ?? 0) === 1 && ($data['IsExist'] ?? '') === 'Y';
    }

    public function checkBarcode(string $barcode): bool
    {
        $data = $this->responseData($this->post('/B2CInvoice/CheckBarcode', [
            'MerchantID' => $this->merchantId,
            'BarCode'    => $barcode,
        ]));

        return (int) ($data['RtnCode'] ?? 0) === 1 && ($data['IsExist'] ?? '') === 'Y';
    }

    public function companyName(string $taxId): ?string
    {
        $data = $this->responseData($this->post('/B2CInvoice/GetCompanyNameByTaxID', [
            'MerchantID'        => $this->merchantId,
            'UnifiedBusinessNo' => $taxId,
        ]));

        if ((int) ($data['RtnCode'] ?? 0) === 1 && isset($data['CompanyName'])) {
            return (string) $data['CompanyName'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function post(string $path, array $data): array
    {
        $response = $this->factory->create('PostWithAesJsonResponseService')->post([
            'MerchantID' => $this->merchantId,
            'RqHeader'   => ['Timestamp' => time()],
            'Data'       => $data,
        ], $this->endpoints->invoiceBaseUrl() . $path);

        return is_array($response) ? $response : [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function responseData(array $payload): array
    {
        $data = $payload['Data'] ?? null;

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resultFromPayload(string $relateNumber, array $payload): InvoiceResult
    {
        $data = $this->responseData($payload);
        $statusCode = (string) ($data['RtnCode'] ?? $payload['TransCode'] ?? '');
        $issuedAt = null;
        $date = $data['InvoiceDate'] ?? null;
        if (is_string($date) && $date !== '') {
            try {
                $issuedAt = Carbon::parse($date);
            } catch (Throwable) {
                $issuedAt = null;
            }
        }

        return new InvoiceResult(
            relateNumber: $relateNumber,
            successful: $statusCode === '1',
            statusCode: $statusCode,
            statusMessage: (string) ($data['RtnMsg'] ?? $payload['TransMsg'] ?? ''),
            invoiceNumber: isset($data['InvoiceNo']) ? (string) $data['InvoiceNo'] : null,
            issuedAt: $issuedAt,
            payload: $payload,
        );
    }
}
