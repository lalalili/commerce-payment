<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Contracts;

use Lalalili\CommercePayment\Data\PaymentResult;

/**
 * host 可插拔的對帳出口：依正規化 PaymentResult 對訂單做狀態更新。
 *
 * 套件附 CommerceCoreReconciler（commerce-core 系 host 用）；非 commerce-core host
 * 自行綁定一個包住自家訂單服務的實作。這是「gateway 純通訊 / 對帳與訂單層解耦」的樞紐。
 */
interface PaymentReconciler
{
    public function reconcile(PaymentResult $result, ?int $updatedBy = null): void;
}
