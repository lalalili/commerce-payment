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

    /**
     * 組綠界信用卡定期定額（Credit Period）建單參數。
     *
     * 在一般 AIO 參數之上附加 PeriodAmount / PeriodType / Frequency / ExecTimes / PeriodReturnURL。
     * 綠界規定：ChoosePayment 固定 Credit、PeriodAmount 必須等於 TotalAmount、ExecTimes ≥ 2。
     *
     * @param array<string, mixed> $context order_number、amount、item_name、return_url、period_return_url、
     *                                       period_amount、period_type、frequency、exec_times
     * @param array<string, mixed> $options merchant_id、trade_desc、item_name_limit…
     * @return array<string, mixed>
     */
    public function makeRecurring(array $context, array $options): array
    {
        // 定期定額僅信用卡支援；強制 ChoosePayment=Credit、不可用銀聯。
        $input = $this->make($context, array_merge($options, ['choose_payment' => 'Credit', 'union_pay' => 2]));

        $periodAmount = isset($context['period_amount'])
            ? number_format((float) $context['period_amount'], 0, '', '')
            : $input['TotalAmount'];

        $input['PeriodAmount'] = $periodAmount;
        $input['PeriodType'] = (string) ($context['period_type'] ?? 'M');
        $input['Frequency'] = (int) ($context['frequency'] ?? 1);
        $input['ExecTimes'] = (int) ($context['exec_times'] ?? 2);

        if (! empty($context['period_return_url'])) {
            $input['PeriodReturnURL'] = (string) $context['period_return_url'];
        }

        return $input;
    }
}
