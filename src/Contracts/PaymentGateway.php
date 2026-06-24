<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Contracts;

use Illuminate\Http\Request;
use Lalalili\CommercePayment\Data\PaymentResult;
use Lalalili\CommercePayment\Data\PaymentStartResult;
use Lalalili\CommercePayment\Data\RefundResult;

/**
 * 單一支付方式的閘道：純通訊（呼叫 API + 驗章 + 對應正規化結果），不寫訂單。
 *
 * 訂單對帳由 host 綁定的 PaymentReconciler 處理（見 PaymentManager::reconcile）。
 */
interface PaymentGateway
{
    /**
     * 發起付款。
     *
     * @param array<string, mixed> $context 中立輸入：order_number、amount、item_name、return_url、result_url…
     */
    public function checkout(array $context): PaymentStartResult;

    /**
     * 僅驗證背景通知的真偽（驗章），不查詢、不對帳。
     *
     * 供需要「快速回 ack、對帳改派佇列」的 async host 使用（避免 ReturnURL 逾時）。
     */
    public function verifyNotify(Request $request): bool;

    /**
     * 僅驗證導回頁（browser redirect / OrderResultURL）的真偽，不查詢、不對帳。
     *
     * 與 verifyNotify 對稱：供 host 在導回流程「驗章失敗即導向錯誤頁、不對帳」使用。
     */
    public function verifyReturn(Request $request): bool;

    /**
     * 處理背景通知（server-to-server），回正規化結果。
     */
    public function handleNotify(Request $request): PaymentResult;

    /**
     * 處理導回頁（browser redirect），回正規化結果。
     */
    public function handleReturn(Request $request): PaymentResult;

    /**
     * 主動查詢交易狀態，回正規化結果。
     */
    public function query(string $orderNumber): PaymentResult;

    /**
     * 退款 / 退刷 / 取消授權。
     *
     * @param array<string, mixed> $context 中立輸入：order_number、amount、trade_no…
     */
    public function refund(array $context): RefundResult;

    /**
     * 背景通知須回應給金流廠商的固定字串（如 ECPay '1|OK'）。
     */
    public function notifyAck(): string;
}
