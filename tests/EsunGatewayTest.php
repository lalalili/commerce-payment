<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Lalalili\CommercePayment\Data\PaymentStartResult;
use Lalalili\CommercePayment\Enums\PaymentOutcome;
use Lalalili\CommercePayment\Gateways\EsunGateway;

/**
 * @param array<string, mixed> $overrides
 */
function esunCfg(array $overrides = []): array
{
    return array_merge([
        'driver'          => 'esun',
        'mid'             => '8089023786',
        'mac_key'         => 'mackey',
        'tid'             => 'EC000001',
        'order_url'       => 'https://esun.test/order',
        'query_url'       => 'https://esun.test/query',
        'cancel_url'      => 'https://esun.test/cancel',
        'status_messages' => ['00' => '核准', '19' => '授權成功(可請款)', '11' => '授權失敗', '69' => '退貨成功'],
        'gateway_label'   => '信用卡',
    ], $overrides);
}

function esunPartial(string $response, array $cfg = []): EsunGateway
{
    $service = Mockery::mock(EsunGateway::class, [esunCfg($cfg)])->makePartial();
    $service->shouldReceive('sendGuzzleRequest')->andReturn($response);

    /** @var EsunGateway $service */
    return $service;
}

/**
 * @param array<string, mixed> $txnData
 */
function esunResp(array $txnData, string $returnCode = '00'): string
{
    return 'DATA=' . json_encode(['returnCode' => $returnCode, 'txnData' => $txnData, 'version' => '2'], JSON_UNESCAPED_SLASHES);
}

it('checkout 組 data/mac/ksn 表單', function (): void {
    $result = (new EsunGateway(esunCfg()))->checkout([
        'order_number' => '240101ESU', 'amount' => 450, 'return_url' => 'https://host.test/return',
    ]);

    $expectedData = json_encode([
        'ONO' => '240101ESU', 'U' => 'https://host.test/return', 'MID' => '8089023786', 'TA' => 450, 'TID' => 'EC000001',
    ], JSON_UNESCAPED_SLASHES);

    expect($result)->toBeInstanceOf(PaymentStartResult::class)
        ->and($result->fields['data'])->toBe($expectedData)
        ->and($result->fields['mac'])->toBe(hash('sha256', $expectedData . 'mackey'))
        ->and($result->url)->toBe('https://esun.test/order');
});

it('notify 固定 Pending（玉山無背景通知）+ ack 0|FAIL', function (): void {
    $gw = new EsunGateway(esunCfg());
    expect($gw->handleNotify(Request::create('/', 'POST'))->outcome)->toBe(PaymentOutcome::Pending)
        ->and($gw->notifyAck())->toBe('0|FAIL');
});

it('query RC00 + SETTLESTATUS19 → Paid，對帳訊息取 19', function (): void {
    $result = esunPartial(esunResp([
        'RC' => '00', 'SETTLESTATUS' => '19', 'SETTLEAMOUNT' => 450, 'LTD' => '2024-01-01', 'LTT' => '12:00:00', 'AIR' => 'T1',
    ]))->query('240101ESU');

    expect($result->outcome)->toBe(PaymentOutcome::Paid)
        ->and($result->amount)->toBe(450)
        ->and($result->statusMessage)->toBe('核准')
        ->and($result->orderMessage())->toBe('授權成功(可請款)');
});

it('query SETTLESTATUS11 → Declined', function (): void {
    expect(esunPartial(esunResp(['RC' => '11', 'SETTLESTATUS' => '11']))->query('A')->outcome)
        ->toBe(PaymentOutcome::Declined);
});

it('query RC69 → Refunded，訊息銀行已退款', function (): void {
    $result = esunPartial(esunResp(['RC' => '69', 'SETTLESTATUS' => '59']))->query('A');
    expect($result->outcome)->toBe(PaymentOutcome::Refunded)->and($result->orderMessage())->toBe('銀行已退款');
});

it('handleReturn GR → UserCancelled', function (): void {
    $result = (new EsunGateway(esunCfg()))->handleReturn(
        Request::create('/', 'POST', ['DATA' => 'ONO=240101ESU,RC=GR']),
    );

    expect($result->outcome)->toBe(PaymentOutcome::UserCancelled)
        ->and($result->orderMessage())->toBe('使用者取消刷卡頁面');
});

it('refund returnCode00+RC00 → refunded', function (): void {
    $result = esunPartial(esunResp(['RC' => '00', 'ONO' => 'A']))->refund(['order_number' => 'A']);
    expect($result->refunded)->toBeTrue()->and($result->statusCode)->toBe('00');
});
