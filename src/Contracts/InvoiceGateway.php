<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Contracts;

use Lalalili\CommercePayment\Data\InvoiceResult;

/**
 * 綠界 B2C 電子發票閘道：純通訊（呼叫 API + AES-JSON），不寫訂單。
 *
 * host-agnostic：輸入皆為中立陣列；發票類型→載具/捐贈的對應由 host 解析後以 invoice_fields 傳入。
 */
interface InvoiceGateway
{
    /**
     * 開立發票。
     *
     * @param array<string, mixed> $context relate_number、customer_email、tax_type、
     *                                       items（[{title,qty,sales_price}]）、
     *                                       invoice_fields（host 已解析的載具/捐贈 ECPay 欄位）
     */
    public function issue(array $context): InvoiceResult;

    /**
     * 作廢發票。
     *
     * @param array<string, mixed> $context invoice_number、reason
     */
    public function void(array $context): InvoiceResult;

    /**
     * 依關聯號查詢發票。
     */
    public function query(string $relateNumber): InvoiceResult;

    /**
     * 驗證愛心碼是否存在。
     */
    public function checkLoveCode(string $loveCode): bool;

    /**
     * 驗證手機條碼是否正確。
     */
    public function checkBarcode(string $barcode): bool;

    /**
     * 依統一編號取得公司名稱（查無回 null）。
     */
    public function companyName(string $taxId): ?string;
}
