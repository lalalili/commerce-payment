<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Contracts;

use Illuminate\Http\Request;
use Lalalili\CommercePayment\Data\PaymentStartResult;
use Lalalili\CommercePayment\Data\RecurringActionResult;
use Lalalili\CommercePayment\Data\RecurringPaymentResult;

/**
 * 定期定額（recurring / 綠界信用卡 Period）能力。
 *
 * 屬選用 contract：定期定額僅綠界信用卡支援，故與一般 PaymentGateway 分離，
 * 避免污染不支援此功能的閘道（如 EsunGateway）。host 以 instanceof 判斷後再呼叫。
 */
interface RecurringPaymentGateway
{
    /**
     * 發起定期定額付款（首期＝開通，後續由金流依週期自動扣款）。
     *
     * @param array<string, mixed> $context 中立輸入：order_number、amount、item_name、return_url、
     *                                      period_return_url、period_amount、period_type、frequency、exec_times
     */
    public function startRecurring(array $context): PaymentStartResult;

    /**
     * 僅驗證定期定額通知（含第 2 期起的 PeriodReturnURL）的真偽（驗章），不查詢、不對帳。
     */
    public function verifyRecurringNotify(Request $request): bool;

    /**
     * 處理定期定額單期通知，回正規化結果。
     */
    public function handleRecurringNotify(Request $request): RecurringPaymentResult;

    /**
     * 取消定期定額（停止後續自動扣款）。
     *
     * @param array<string, mixed> $context 中立輸入：order_number（原始首期訂單號）
     */
    public function cancelRecurring(array $context): RecurringActionResult;

    /**
     * 主動查詢定期定額狀態（各期授權明細 / 成功次數），回正規化結果。
     */
    public function queryRecurring(string $orderNumber): RecurringPaymentResult;
}
