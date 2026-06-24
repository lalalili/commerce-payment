<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Support;

use Illuminate\Support\Str;

/**
 * 組綠界 AIO 結帳參數。各支付方式的差異（TradeDesc、ChoosePayment、UnionPay、ItemName 上限、
 * 是否帶 OrderResultURL）由 method options 驅動，使同一 EcpayGateway 可服務信用卡 / 銀聯卡。
 */
class EcpayCheckoutPayloadFactory
{
    /**
     * @param array<string, mixed> $context order_number、amount、item_name、return_url、result_url
     * @param array<string, mixed> $options merchant_id、trade_desc、choose_payment、union_pay、item_name_limit
     * @return array<string, mixed>
     */
    public function make(array $context, array $options): array
    {
        $itemNameLimit = (int) ($options['item_name_limit'] ?? 200);

        $input = [
            'MerchantID'        => (string) ($options['merchant_id'] ?? ''),
            'MerchantTradeNo'   => (string) ($context['order_number'] ?? ''),
            'MerchantTradeDate' => date('Y/m/d H:i:s'),
            'PaymentType'       => 'aio',
            'TotalAmount'       => number_format((float) ($context['amount'] ?? 0), 0, '', ''),
            'TradeDesc'         => (string) ($options['trade_desc'] ?? '信用卡付款'),
            // ECPay 官方建議 ≤200 字元（上限 400）；過長截斷易致多位元組亂碼 → CheckMacValue 不一致 → 掉單。
            'ItemName'      => Str::limit(rtrim((string) ($context['item_name'] ?? ''), '#'), $itemNameLimit),
            'ChoosePayment' => (string) ($options['choose_payment'] ?? 'Credit'),
            'EncryptType'   => 1,
            // 0：消費者可選銀聯；1：只用銀聯卡並直接導到銀聯；2：不可用銀聯。
            'UnionPay'          => (int) ($options['union_pay'] ?? 2),
            'NeedExtraPaidInfo' => 'N',
            'ReturnURL'         => (string) ($context['return_url'] ?? ''),
        ];

        if (! empty($context['result_url'])) {
            $input['OrderResultURL'] = (string) $context['result_url'];
        }

        return $input;
    }
}
