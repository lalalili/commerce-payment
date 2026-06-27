<?php

declare(strict_types=1);

use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Response\VerifiedArrayResponse;
use Lalalili\CommercePayment\Data\PaymentStartResult;
use Lalalili\CommercePayment\Enums\PaymentOutcome;
use Lalalili\CommercePayment\Gateways\EcpayGateway;
use Lalalili\CommercePayment\Support\EcpayCheckoutPayloadFactory;
use Mockery\MockInterface;

/**
 * @param array<string, mixed> $overrides
 */
function ecpayCfg(array $overrides = []): array
{
    return array_merge([
        'driver'          => 'ecpay',
        'merchant_id'     => '2000132',
        'hash_key'        => 'key',
        'hash_iv'         => 'iv',
        'union_pay'       => 1,
        'trade_desc'      => '銀聯卡支付',
        'item_name_limit' => 200,
        'gateway_label'   => '銀聯卡',
    ], $overrides);
}

function ecpayGw(Factory $factory, array $cfg = []): EcpayGateway
{
    return new EcpayGateway(ecpayCfg($cfg), new EcpayCheckoutPayloadFactory(), $factory);
}

it('checkout 依 method 設定組表單（UnionPay/ItemName limit）', function (): void {
    $formService = new class () {
        /** @param array<string, mixed> $input */
        public function generate(array $input, string $url): string
        {
            return '<form>ok</form>';
        }
    };
    $factory = Mockery::mock(Factory::class, function (MockInterface $m) use ($formService): void {
        $m->shouldReceive('create')->once()->with('AutoSubmitFormWithCmvService')->andReturn($formService);
    });

    $result = ecpayGw($factory)->checkout([
        'order_number' => '240101AAA',
        'amount'       => 450,
        'item_name'    => str_repeat('書', 250) . '#',
        'return_url'   => 'https://host.test/notify',
    ]);

    expect($result)->toBeInstanceOf(PaymentStartResult::class)
        ->and($result->fields['UnionPay'])->toBe(1)
        ->and($result->fields['TradeDesc'])->toBe('銀聯卡支付')
        ->and($result->fields['ReturnURL'])->toBe('https://host.test/notify')
        ->and(mb_strlen((string) $result->fields['ItemName']))->toBeLessThanOrEqual(200)
        ->and($result->fields)->not->toHaveKey('OrderResultURL');
});

it('checkout 帶 result_url 時加入 OrderResultURL', function (): void {
    $formService = new class () {
        /** @param array<string, mixed> $input */
        public function generate(array $input, string $url): string
        {
            return '';
        }
    };
    $factory = Mockery::mock(Factory::class, function (MockInterface $m) use ($formService): void {
        $m->shouldReceive('create')->once()->andReturn($formService);
    });

    $result = ecpayGw($factory, ['union_pay' => 2])->checkout([
        'order_number' => 'A', 'amount' => 100, 'return_url' => 'https://n', 'result_url' => 'https://r',
    ]);

    expect($result->fields['OrderResultURL'])->toBe('https://r')
        ->and($result->fields['UnionPay'])->toBe(2);
});

it('query TradeStatus 1 → Paid 帶 amount/paidAt', function (): void {
    $post = new class () {
        /** @param array<string, mixed> $input */
        public function post(array $input, string $url): array
        {
            return [
                'MerchantTradeNo' => '240101AAA',
                'TradeStatus'     => '1',
                'TradeAmt'        => '450',
                'TradeDate'       => '2024/01/01 00:00:00',
                'TradeNo'         => 'T1',
            ];
        }
    };
    $verifier = new class () {
        /** @param array<string, mixed> $d */
        public function verify(array $d): void
        {
        }
    };
    $factory = Mockery::mock(Factory::class, function (MockInterface $m) use ($post, $verifier): void {
        $m->shouldReceive('create')->once()->with('PostWithCmvVerifiedEncodedStrResponseService')->andReturn($post);
        $m->shouldReceive('create')->once()->with(VerifiedArrayResponse::class)->andReturn($verifier);
    });

    $result = ecpayGw($factory)->query('240101AAA');

    expect($result->outcome)->toBe(PaymentOutcome::Paid)
        ->and($result->amount)->toBe(450)
        ->and($result->tradeNo)->toBe('T1')
        ->and($result->gatewayLabel)->toBe('銀聯卡')
        ->and($result->paidAt)->not->toBeNull();
});

it('query 驗章失敗 → QueryFailed', function (): void {
    $post = new class () {
        /** @param array<string, mixed> $input */
        public function post(array $input, string $url): array
        {
            return ['TradeStatus' => '1'];
        }
    };
    $verifier = new class () {
        /** @param array<string, mixed> $d */
        public function verify(array $d): void
        {
            throw new RuntimeException('verify fail');
        }
    };
    $factory = Mockery::mock(Factory::class, function (MockInterface $m) use ($post, $verifier): void {
        $m->shouldReceive('create')->once()->with('PostWithCmvVerifiedEncodedStrResponseService')->andReturn($post);
        $m->shouldReceive('create')->once()->with(VerifiedArrayResponse::class)->andReturn($verifier);
    });

    expect(ecpayGw($factory)->query('A')->outcome)->toBe(PaymentOutcome::QueryFailed);
});

it('refund RtnCode 1 → refunded', function (): void {
    $post = new class () {
        /** @param array<string, mixed> $input */
        public function post(array $input, string $url): array
        {
            return ['RtnCode' => '1', 'RtnMsg' => '退刷成功'];
        }
    };
    $factory = Mockery::mock(Factory::class, function (MockInterface $m) use ($post): void {
        $m->shouldReceive('create')->once()->with('PostWithCmvVerifiedEncodedStrResponseService')->andReturn($post);
    });

    $result = ecpayGw($factory)->refund(['order_number' => 'A', 'trade_no' => 'T1', 'amount' => 450]);

    expect($result->refunded)->toBeTrue()->and($result->statusCode)->toBe('1');
});

it('refund 無 tradeNo → 失敗不打 API', function (): void {
    $factory = Mockery::mock(Factory::class);

    $result = ecpayGw($factory)->refund(['order_number' => 'A', 'amount' => 450]);

    expect($result->refunded)->toBeFalse();
});

function ecpayNotifyRequest(): \Illuminate\Http\Request
{
    return \Illuminate\Http\Request::create('/', 'POST', [
        'MerchantID'    => '2000132', 'MerchantTradeNo' => '240101AAA', 'PaymentDate' => '2024/01/01 00:00:00',
        'PaymentType'   => 'Credit_CreditCard', 'PaymentTypeChargeFee' => '1', 'RtnCode' => '1', 'RtnMsg' => 'OK',
        'SimulatePaid'  => '0', 'TradeAmt' => '450', 'TradeDate' => '2024/01/01 00:00:00', 'TradeNo' => 'T1',
        'CheckMacValue' => 'dummy',
    ]);
}

it('verifyNotify 驗章成功 → true（不查詢）', function (): void {
    $verifier = new class () {
        /** @param array<string, mixed> $d */
        public function get(array $d): string
        {
            return '1|OK';
        }
    };
    $factory = Mockery::mock(Factory::class, function (MockInterface $m) use ($verifier): void {
        $m->shouldReceive('create')->once()->with(VerifiedArrayResponse::class)->andReturn($verifier);
    });

    expect(ecpayGw($factory)->verifyNotify(ecpayNotifyRequest()))->toBeTrue();
});

it('verifyNotify 驗章失敗 → false', function (): void {
    $verifier = new class () {
        /** @param array<string, mixed> $d */
        public function get(array $d): string
        {
            throw new RuntimeException('fail');
        }
    };
    $factory = Mockery::mock(Factory::class, function (MockInterface $m) use ($verifier): void {
        $m->shouldReceive('create')->once()->with(VerifiedArrayResponse::class)->andReturn($verifier);
    });

    expect(ecpayGw($factory)->verifyNotify(ecpayNotifyRequest()))->toBeFalse();
});

it('verifyReturn 驗章成功（回 MerchantTradeNo 陣列）→ true', function (): void {
    $verifier = new class () {
        /**
         * @param array<string, mixed> $d
         * @return array<string, mixed>
         */
        public function get(array $d): array
        {
            return ['MerchantTradeNo' => 'A0000001', 'RtnCode' => '1'];
        }
    };
    $factory = Mockery::mock(Factory::class, function (MockInterface $m) use ($verifier): void {
        $m->shouldReceive('create')->once()->with(VerifiedArrayResponse::class)->andReturn($verifier);
    });

    expect(ecpayGw($factory)->verifyReturn(ecpayNotifyRequest()))->toBeTrue();
});

it('verifyReturn 驗章失敗 → false', function (): void {
    $verifier = new class () {
        /** @param array<string, mixed> $d */
        public function get(array $d): array
        {
            throw new RuntimeException('fail');
        }
    };
    $factory = Mockery::mock(Factory::class, function (MockInterface $m) use ($verifier): void {
        $m->shouldReceive('create')->once()->with(VerifiedArrayResponse::class)->andReturn($verifier);
    });

    expect(ecpayGw($factory)->verifyReturn(ecpayNotifyRequest()))->toBeFalse();
});

it('startRecurring 組定期定額表單（PeriodAmount=TotalAmount、Credit、ExecTimes）', function (): void {
    $captured = [];
    $formService = new class ($captured) {
        /** @param array<string, mixed> $captured */
        public function __construct(public array &$captured)
        {
        }

        /** @param array<string, mixed> $input */
        public function generate(array $input, string $url): string
        {
            $this->captured = $input;

            return '<form>recurring</form>';
        }
    };
    $factory = Mockery::mock(Factory::class, function (MockInterface $m) use ($formService): void {
        $m->shouldReceive('create')->once()->with('AutoSubmitFormWithCmvService')->andReturn($formService);
    });

    $result = ecpayGw($factory)->startRecurring([
        'order_number'      => 'SUB0001',
        'amount'            => 300,
        'item_name'         => '訂閱方案',
        'return_url'        => 'https://host.test/notify',
        'period_return_url' => 'https://host.test/period',
        'period_type'       => 'M',
        'frequency'         => 1,
        'exec_times'        => 999,
    ]);

    expect($result)->toBeInstanceOf(PaymentStartResult::class)
        ->and($result->fields['ChoosePayment'])->toBe('Credit')
        ->and($result->fields['TotalAmount'])->toBe('300')
        ->and($result->fields['PeriodAmount'])->toBe('300')
        ->and($result->fields['PeriodType'])->toBe('M')
        ->and($result->fields['Frequency'])->toBe(1)
        ->and($result->fields['ExecTimes'])->toBe(999)
        ->and($result->fields['PeriodReturnURL'])->toBe('https://host.test/period')
        ->and($result->fields['UnionPay'])->toBe(2);
});

function ecpayPeriodNotifyRequest(string $rtnCode = '1', int $totalSuccessTimes = 2): \Illuminate\Http\Request
{
    return \Illuminate\Http\Request::create('/', 'POST', [
        'MerchantID'        => '2000132', 'MerchantTradeNo' => 'SUB0001', 'RtnCode' => $rtnCode, 'RtnMsg' => 'OK',
        'PeriodType'        => 'M', 'Frequency' => '1', 'ExecTimes' => '999', 'Amount' => '300',
        'gwsr'              => '11122233', 'ProcessDate' => '2024/02/01 00:00:00', 'AuthCode' => '777777',
        'TotalSuccessTimes' => (string) $totalSuccessTimes, 'card4no' => '1111', 'card6no' => '431195',
        'SimulatePaid'      => '0', 'CheckMacValue' => 'dummy',
    ]);
}

it('handleRecurringNotify RtnCode 1 → Paid 帶 totalSuccessTimes/gwsr/amount', function (): void {
    $verifier = new class () {
        /** @param array<string, mixed> $d */
        public function get(array $d): string
        {
            return '1|OK';
        }
    };
    $factory = Mockery::mock(Factory::class, function (MockInterface $m) use ($verifier): void {
        $m->shouldReceive('create')->once()->with(VerifiedArrayResponse::class)->andReturn($verifier);
    });

    $result = ecpayGw($factory)->handleRecurringNotify(ecpayPeriodNotifyRequest('1', 2));

    expect($result->outcome)->toBe(PaymentOutcome::Paid)
        ->and($result->orderNumber)->toBe('SUB0001')
        ->and($result->amount)->toBe(300)
        ->and($result->totalSuccessTimes)->toBe(2)
        ->and($result->gwsr)->toBe('11122233')
        ->and($result->periodType)->toBe('M')
        ->and($result->paidAt)->not->toBeNull();
});

it('handleRecurringNotify RtnCode 非 1 → Declined', function (): void {
    $verifier = new class () {
        /** @param array<string, mixed> $d */
        public function get(array $d): string
        {
            return '1|OK';
        }
    };
    $factory = Mockery::mock(Factory::class, function (MockInterface $m) use ($verifier): void {
        $m->shouldReceive('create')->once()->with(VerifiedArrayResponse::class)->andReturn($verifier);
    });

    $result = ecpayGw($factory)->handleRecurringNotify(ecpayPeriodNotifyRequest('10100058', 0));

    expect($result->outcome)->toBe(PaymentOutcome::Declined)
        ->and($result->isPaid())->toBeFalse();
});

it('cancelRecurring RtnCode 1 → success', function (): void {
    $captured = [];
    $post = new class ($captured) {
        /** @param array<string, mixed> $captured */
        public function __construct(public array &$captured)
        {
        }

        /**
         * @param array<string, mixed> $input
         * @return array<string, mixed>
         */
        public function post(array $input, string $url): array
        {
            $this->captured = ['input' => $input, 'url' => $url];

            return ['RtnCode' => '1', 'RtnMsg' => '成功'];
        }
    };
    $factory = Mockery::mock(Factory::class, function (MockInterface $m) use ($post): void {
        $m->shouldReceive('create')->once()->with('PostWithCmvVerifiedEncodedStrResponseService')->andReturn($post);
    });

    $result = ecpayGw($factory)->cancelRecurring(['order_number' => 'SUB0001']);

    expect($result->success)->toBeTrue()
        ->and($result->statusCode)->toBe('1')
        ->and($post->captured['input']['Action'])->toBe('Cancel')
        ->and($post->captured['url'])->toContain('/Cashier/CreditCardPeriodAction');
});

it('cancelRecurring 無 order_number → 失敗不打 API', function (): void {
    $factory = Mockery::mock(Factory::class);

    $result = ecpayGw($factory)->cancelRecurring([]);

    expect($result->success)->toBeFalse();
});
