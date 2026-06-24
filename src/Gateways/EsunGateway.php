<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Gateways;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Lalalili\CommercePayment\Contracts\PaymentGateway;
use Lalalili\CommercePayment\Data\PaymentResult;
use Lalalili\CommercePayment\Data\PaymentStartResult;
use Lalalili\CommercePayment\Data\RefundResult;
use Lalalili\CommercePayment\Enums\PaymentOutcome;
use Lalalili\CommercePayment\Support\AutoSubmitForm;
use Lalalili\CommercePayment\Support\InteractsWithHttp;
use Psr\Http\Message\ResponseInterface;

/**
 * 玉山銀行（E.SUN）信用卡閘道。純通訊，設定由 config 注入。
 *
 * 玉山無背景通知（handleNotify 回 Pending），對帳以導回頁（handleReturn）同步查詢與排程補查為準。
 */
class EsunGateway implements PaymentGateway
{
    use AutoSubmitForm;
    use InteractsWithHttp;

    private string $mid;

    private string $macKey;

    private string $tid;

    private string $orderUrl;

    private string $queryUrl;

    private string $cancelUrl;

    /** @var array<array-key, string> */
    private array $statusMessages;

    private string $gatewayLabel;

    /**
     * @param array<string, mixed> $config mid、mac_key、tid、order_url、query_url、cancel_url、
     *                                      status_messages、gateway_label
     */
    public function __construct(private readonly array $config)
    {
        $this->mid = (string) ($config['mid'] ?? '');
        $this->macKey = (string) ($config['mac_key'] ?? '');
        $this->tid = (string) ($config['tid'] ?? 'EC000001');
        $this->orderUrl = (string) ($config['order_url'] ?? '');
        $this->queryUrl = (string) ($config['query_url'] ?? '');
        $this->cancelUrl = (string) ($config['cancel_url'] ?? '');
        /** @var array<array-key, string> $messages */
        $messages = $config['status_messages'] ?? [];
        $this->statusMessages = $messages;
        $this->gatewayLabel = (string) ($config['gateway_label'] ?? '信用卡');
    }

    /**
     * @param array<string, mixed> $context
     */
    public function checkout(array $context): PaymentStartResult
    {
        $data = json_encode([
            'ONO' => (string) ($context['order_number'] ?? ''),
            'U'   => (string) ($context['return_url'] ?? ''),
            'MID' => $this->mid,
            'TA'  => $context['amount'] ?? 0,
            'TID' => $this->tid,
        ], JSON_UNESCAPED_SLASHES);
        $parameters = [
            'data' => $data,
            'mac'  => hash('sha256', $data . $this->macKey),
            'ksn'  => 1,
        ];

        return PaymentStartResult::autoPostForm(
            $this->orderUrl,
            $parameters,
            $this->buildAutoSubmitHtml($this->orderUrl, $parameters),
        );
    }

    public function verifyNotify(Request $request): bool
    {
        // 玉山無背景通知。
        return false;
    }

    public function handleNotify(Request $request): PaymentResult
    {
        // 玉山無背景通知。
        return PaymentResult::pending('', '', '玉山無背景通知', []);
    }

    public function handleReturn(Request $request): PaymentResult
    {
        $returnDataArray = $this->parseDataString((string) $request->input('DATA'));
        $orderNumber = (string) ($returnDataArray['ONO'] ?? '');
        $returnCode = (string) ($returnDataArray['RC'] ?? '');

        if ($orderNumber === '' || $returnCode === '') {
            return PaymentResult::queryFailed($orderNumber, '01', ['message' => '查詢失敗']);
        }

        if ($returnCode === 'GR') {
            return new PaymentResult(
                orderNumber: $orderNumber,
                outcome: PaymentOutcome::UserCancelled,
                statusCode: 'GR',
                statusMessage: $this->statusMessages['GR'] ?? '',
                payload: $request->all(),
                outcomeMessage: '使用者取消刷卡頁面',
            );
        }

        // 非取消：以查詢結果為準（玉山導回頁同步查詢確認）。
        return $this->query($orderNumber);
    }

    public function query(string $orderNumber): PaymentResult
    {
        $data = json_encode(['MID' => $this->mid, 'ONO' => $orderNumber], JSON_UNESCAPED_SLASHES);
        $parameters = [
            'data' => $data,
            'mac'  => hash('sha256', $data . $this->macKey),
            'ksn'  => 1,
        ];

        $raw = $this->postEsun($this->queryUrl, $parameters, 'query');
        $txnData = $raw['DATA']['txnData'] ?? null;

        if (! is_array($txnData) || ! isset($txnData['RC'], $txnData['SETTLESTATUS'])) {
            return PaymentResult::queryFailed($orderNumber, '01', ['message' => '查詢失敗']);
        }

        return $this->interpretTxnData($orderNumber, $raw, $txnData);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function refund(array $context): RefundResult
    {
        $orderNumber = (string) ($context['order_number'] ?? '');
        $data = json_encode(['MID' => $this->mid, 'ONO' => $orderNumber], JSON_UNESCAPED_SLASHES);
        $parameters = [
            'data' => $data,
            'mac'  => hash('sha256', $data . $this->macKey),
            'ksn'  => 1,
        ];

        $raw = $this->postEsun($this->cancelUrl, $parameters, 'refund');
        $txnData = $raw['DATA']['txnData'] ?? null;
        if (! is_array($txnData) || ! isset($txnData['RC'])) {
            return new RefundResult($orderNumber, false, '', '玉山取消授權無有效回應', $raw);
        }

        $rc = (string) $txnData['RC'];
        $returnCode = (string) ($raw['DATA']['returnCode'] ?? '');

        return new RefundResult(
            $orderNumber,
            $returnCode === '00' && $rc === '00',
            $rc,
            $this->statusMessages[$rc] ?? '',
            $raw,
        );
    }

    public function notifyAck(): string
    {
        return '0|FAIL';
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $txnData
     */
    private function interpretTxnData(string $orderNumber, array $raw, array $txnData): PaymentResult
    {
        $statusCode = (string) $txnData['RC'];
        $statusMessage = $this->statusMessages[$statusCode] ?? '請洽詢銀行';
        $settleStatus = (string) $txnData['SETTLESTATUS'];
        $tradeNo = isset($txnData['AIR']) ? (string) $txnData['AIR'] : null;

        if ($statusCode === '00' && $settleStatus === '19' && isset($txnData['SETTLEAMOUNT'])) {
            $paidAt = Carbon::parse(
                ($txnData['LTD'] ?? '') . ' ' . ($txnData['LTT'] ?? ''),
                config('app.timezone'),
            );

            return PaymentResult::paid(
                $orderNumber,
                $statusCode,
                $statusMessage,
                $raw,
                (int) $txnData['SETTLEAMOUNT'],
                $paidAt,
                $this->gatewayLabel,
                $tradeNo,
                $this->statusMessages[$settleStatus] ?? $statusMessage,
            );
        }

        if ($settleStatus === '11') {
            return new PaymentResult($orderNumber, PaymentOutcome::Declined, $statusCode, $statusMessage, $raw, tradeNo: $tradeNo);
        }

        if ($statusCode === '69') {
            return new PaymentResult($orderNumber, PaymentOutcome::Refunded, $statusCode, $statusMessage, $raw, tradeNo: $tradeNo, outcomeMessage: '銀行已退款');
        }

        return PaymentResult::pending($orderNumber, $statusCode, $statusMessage, $raw, $tradeNo);
    }

    /**
     * @param array<string, scalar> $parameters
     * @return array<string, mixed>
     */
    private function postEsun(string $url, array $parameters, string $label): array
    {
        $body = '';
        try {
            $body = $this->sendGuzzleRequest('POST', $url, $parameters);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                if ($response instanceof ResponseInterface) {
                    $body = (string) $response->getBody();
                }
                Log::channel('slack_outside_api')->critical("EsunGateway:{$label}:玉山請求失敗：" . $e->getMessage() . $body);
            }
        } catch (ConnectException $e) {
            $body = $e->getMessage();
            Log::channel('slack_outside_api')->critical("EsunGateway:{$label}:玉山請求失敗：" . $body);
        }

        $parts = explode('=', $body, 2);
        if (count($parts) !== 2) {
            return [];
        }

        [$key, $value] = $parts;
        $decoded = json_decode($value, true);

        return is_array($decoded) ? [$key => $decoded] : [];
    }

    /**
     * @return array<string, string>
     */
    private function parseDataString(string $dataString): array
    {
        $result = [];
        foreach (explode(',', $dataString) as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) === 2) {
                $result[$parts[0]] = $parts[1];
            }
        }

        return $result;
    }
}
