<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Enums;

/**
 * 金流閘道回應的正規化結果。各 gateway 把自家狀態碼對應到此列舉，
 * 對帳器（PaymentReconciler）依此處理訂單，不需認得各家原始碼。
 */
enum PaymentOutcome: string
{
    case Paid = 'paid';
    case Declined = 'declined';
    case Refunded = 'refunded';
    case UserCancelled = 'user_cancelled';
    case Pending = 'pending';
    case QueryFailed = 'query_failed';
}
