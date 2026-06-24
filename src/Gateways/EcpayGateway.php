<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Gateways;

use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Response\VerifiedArrayResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Lalalili\CommercePayment\Contracts\PaymentGateway;
use Lalalili\CommercePayment\Data\PaymentResult;
use Lalalili\CommercePayment\Data\PaymentStartResult;
use Lalalili\CommercePayment\Data\RefundResult;
use Lalalili\CommercePayment\Support\EcpayCheckoutPayloadFactory;
use Lalalili\CommercePayment\Support\EcpayEndpointResolver;
use Throwable;

/**
 * 綠界 ECPay AIO 信用卡 / 銀聯卡閘道。純通訊，設定（卡別/金鑰/payload 選項）由 config 注入。
 *
 * 同一 class 透過 method options（union_pay / trade_desc / choose_payment / item_name_limit）
 * 服務「信用卡」與「銀聯卡」兩種支付方式。
 *
 * @see https://github.com/ECPay/SDK_PHP
 */
class EcpayGateway implements PaymentGateway
{
    private string $merchantId;

    private string $hashKey;

    private string $hashIv;

    private EcpayEndpointResolver $endpoints;

    private Factory $factory;

    /**
     * @param array<string, mixed> $config merchant_id、hash_key、hash_iv、union_pay、trade_desc、
     *                                      choose_payment、item_name_limit、gateway_label、stage_merchant_ids
     */
    public function __construct(
        private readonly array $config,
        private readonly EcpayCheckoutPayloadFactory $payloadFactory = new EcpayCheckoutPayloadFactory(),
        ?Factory $factory = null,
    ) {
        $this->merchantId = (string) ($config['merchant_id'] ?? '');
        $this->hashKey = (string) ($config['hash_key'] ?? '');
        $this->hashIv = (string) ($config['hash_iv'] ?? '');
        /** @var array<int, string> $stage */
        $stage = $config['stage_merchant_ids'] ?? ['3002607', '3002599'];
        $this->endpoints = new EcpayEndpointResolver($this->merchantId, $stage);
        $this->factory = $factory ?? new Factory(['hashKey' => $this->hashKey, 'hashIv' => $this->hashIv]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function checkout(array $context): PaymentStartResult
    {
        $input = $this->payloadFactory->make($context, $this->config);
        $url = $this->endpoints->paymentBaseUrl() . '/Cashier/AioCheckOut/V5';
        $html = $this->factory->create('AutoSubmitFormWithCmvService')->generate($input, $url);

        return PaymentStartResult::autoPostForm($url, $input, $html);
    }

    public function verifyNotify(Request $request): bool
    {
        $payload = $this->validatedPaymentPayload($request);
        try {
            return $this->factory->create(VerifiedArrayResponse::class)->get($payload) === '1|OK';
        } catch (Throwable $e) {
            Log::channel('slack_outside_api')->critical('EcpayGateway:verifyNotify:綠界通知驗章失敗：' . $e->getMessage());

            return false;
        }
    }

    public function handleNotify(Request $request): PaymentResult
    {
        $payload = $this->validatedPaymentPayload($request);
        try {
            $verified = $this->factory->create(VerifiedArrayResponse::class)->get($payload);
            if ($verified === '1|OK') {
                return $this->query((string) $payload['MerchantTradeNo']);
            }
        } catch (Throwable $e) {
            Log::channel('slack_outside_api')->critical('EcpayGateway:handleNotify:綠界通知驗證失敗：' . $e->getMessage());
        }

        return $this->resultFromPayload($payload);
    }

    public function handleReturn(Request $request): PaymentResult
    {
        $payload = $this->validatedPaymentPayload($request);
        try {
            $response = $this->factory->create(VerifiedArrayResponse::class)->get($payload);
            if (is_array($response) && isset($response['MerchantTradeNo'])) {
                return $this->query((string) $response['MerchantTradeNo']);
            }
        } catch (Throwable $e) {
            Log::channel('slack_outside_api')->critical('EcpayGateway:handleReturn:綠界導回驗證失敗：' . $e->getMessage());
        }

        return $this->resultFromPayload($payload);
    }

    public function query(string $orderNumber): PaymentResult
    {
        $postService = $this->factory->create('PostWithCmvVerifiedEncodedStrResponseService');
        $payload = $postService->post([
            'MerchantID'      => $this->merchantId,
            'MerchantTradeNo' => $orderNumber,
            'TimeStamp'       => time(),
        ], $this->endpoints->paymentBaseUrl() . '/Cashier/QueryTradeInfo/V5');

        try {
            $this->factory->create(VerifiedArrayResponse::class)->verify($payload);
        } catch (Throwable $e) {
            Log::channel('slack_outside_api')->critical('EcpayGateway:query:綠界查詢驗章失敗：' . $e->getMessage());

            return PaymentResult::queryFailed($orderNumber, '', ['message' => '查詢失敗']);
        }

        return $this->resultFromPayload(is_array($payload) ? $payload : [], $orderNumber);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function refund(array $context): RefundResult
    {
        $orderNumber = (string) ($context['order_number'] ?? '');
        $tradeNo = (string) ($context['trade_no'] ?? '');
        $amount = (int) ($context['amount'] ?? 0);

        if ($tradeNo === '') {
            return new RefundResult($orderNumber, false, '', '無法取得綠界交易編號 TradeNo');
        }

        try {
            $payload = $this->factory->create('PostWithCmvVerifiedEncodedStrResponseService')->post([
                'MerchantID'      => $this->merchantId,
                'MerchantTradeNo' => $orderNumber,
                'TradeNo'         => $tradeNo,
                'Action'          => 'R',
                'TotalAmount'     => $amount,
            ], $this->endpoints->paymentBaseUrl() . '/CreditDetail/DoAction');
        } catch (Throwable $e) {
            Log::channel('slack_outside_api')->critical('EcpayGateway:refund:綠界退刷失敗：' . $e->getMessage());

            return new RefundResult($orderNumber, false, '', '綠界退刷失敗：API例外');
        }

        if (! is_array($payload) || ! isset($payload['RtnCode'], $payload['RtnMsg'])) {
            return new RefundResult($orderNumber, false, '', '綠界退刷失敗：API無回應', is_array($payload) ? $payload : []);
        }

        $rtnCode = (string) $payload['RtnCode'];

        return new RefundResult($orderNumber, $rtnCode === '1', $rtnCode, (string) $payload['RtnMsg'], $payload);
    }

    public function notifyAck(): string
    {
        return '1|OK';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resultFromPayload(array $payload, ?string $fallbackOrderNumber = null): PaymentResult
    {
        $orderNumber = (string) ($payload['MerchantTradeNo'] ?? $fallbackOrderNumber ?? '');
        $status = [
            '0'        => '訂單成立未付款',
            '1'        => '訂單成立已付款',
            '10200095' => '訂單未成立，消費者未完成付款作業',
        ];

        $statusCode = (string) ($payload['TradeStatus'] ?? $payload['RtnCode'] ?? '');
        if ($statusCode === '') {
            return PaymentResult::queryFailed($orderNumber, '', ['message' => '查詢失敗']);
        }

        $statusMessage = (string) ($payload['RtnMsg'] ?? $status[$statusCode] ?? '請洽詢銀行');
        $tradeNo = isset($payload['TradeNo']) ? (string) $payload['TradeNo'] : null;
        $gatewayLabel = (string) ($this->config['gateway_label'] ?? '信用卡');

        if ($statusCode === '1' && isset($payload['TradeAmt'])) {
            $paidAt = $this->parsePaidAt($payload);

            return PaymentResult::paid(
                $orderNumber,
                '1',
                $status['1'],
                $payload,
                (int) $payload['TradeAmt'],
                $paidAt ?? Carbon::now(),
                $gatewayLabel,
                $tradeNo,
            );
        }

        return PaymentResult::pending($orderNumber, $statusCode, $statusMessage, $payload, $tradeNo);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function parsePaidAt(array $payload): ?Carbon
    {
        $date = $payload['PaymentDate'] ?? $payload['TradeDate'] ?? null;
        if (! is_string($date) || $date === '') {
            return null;
        }

        try {
            return Carbon::parse($date, config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPaymentPayload(Request $request): array
    {
        return $request->validate([
            'CustomField1'         => 'nullable',
            'CustomField2'         => 'nullable',
            'CustomField3'         => 'nullable',
            'CustomField4'         => 'nullable',
            'MerchantID'           => 'required',
            'MerchantTradeNo'      => 'required',
            'PaymentDate'          => 'required',
            'PaymentType'          => 'required',
            'PaymentTypeChargeFee' => 'required',
            'RtnCode'              => 'required',
            'RtnMsg'               => 'required',
            'SimulatePaid'         => 'required',
            'StoreID'              => 'nullable',
            'TradeAmt'             => 'required',
            'TradeDate'            => 'required',
            'TradeNo'              => 'required',
            'CheckMacValue'        => 'required',
        ]);
    }
}
