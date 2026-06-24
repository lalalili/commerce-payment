<?php

declare(strict_types=1);

use Ecpay\Sdk\Factories\Factory;
use Lalalili\CommercePayment\Gateways\EcpayInvoiceGateway;
use Mockery\MockInterface;

/**
 * @param array<string, mixed> $response 模擬 AES-JSON 解密後的回應
 */
function invoiceGateway(array $response): EcpayInvoiceGateway
{
    $post = new class ($response) {
        /** @param array<string, mixed> $response */
        public function __construct(private array $response)
        {
        }

        /**
         * @param array<string, mixed> $input
         * @return array<string, mixed>
         */
        public function post(array $input, string $url): array
        {
            return $this->response;
        }
    };

    $factory = Mockery::mock(Factory::class, function (MockInterface $m) use ($post): void {
        $m->shouldReceive('create')->once()->with('PostWithAesJsonResponseService')->andReturn($post);
    });

    return new EcpayInvoiceGateway(
        ['merchant_id' => '2000132', 'hash_key' => 'k', 'hash_iv' => 'i', 'stage_merchant_ids' => ['2000132']],
        $factory,
    );
}

it('issue 成功 → successful + invoiceNumber + issuedAt', function (): void {
    $result = invoiceGateway([
        'TransCode' => 1,
        'Data'      => ['RtnCode' => '1', 'RtnMsg' => '開立成功', 'InvoiceNo' => 'AB12345678', 'InvoiceDate' => '2024-01-01 12:00:00'],
    ])->issue([
        'relate_number'  => '240101AAA-1',
        'customer_email' => 'a@b.com',
        'tax_type'       => 1,
        'items'          => [['title' => '書', 'qty' => 2, 'sales_price' => 100]],
        'invoice_fields' => ['CarrierType' => 1, 'CarrierNum' => ''],
    ]);

    expect($result->successful)->toBeTrue()
        ->and($result->relateNumber)->toBe('240101AAA-1')
        ->and($result->invoiceNumber)->toBe('AB12345678')
        ->and($result->issuedAt)->not->toBeNull();
});

it('issue 失敗（RtnCode 非 1）→ not successful', function (): void {
    $result = invoiceGateway([
        'TransCode' => 1,
        'Data'      => ['RtnCode' => '0', 'RtnMsg' => '開立失敗'],
    ])->issue(['relate_number' => 'X', 'items' => []]);

    expect($result->successful)->toBeFalse()
        ->and($result->statusMessage)->toBe('開立失敗');
});

it('void → 帶回 invoiceNumber', function (): void {
    $result = invoiceGateway([
        'TransCode' => 1,
        'Data'      => ['RtnCode' => '1', 'RtnMsg' => '作廢成功'],
    ])->void([
        'relate_number'  => 'X',
        'invoice_number' => 'AB12345678',
        'reason'         => '訂單取消',
        'invoice_fields' => ['InvoiceDate' => '2024-01-01'],
    ]);

    expect($result->successful)->toBeTrue()
        ->and($result->invoiceNumber)->toBe('AB12345678');
});

it('query → InvoiceResult', function (): void {
    $result = invoiceGateway([
        'TransCode' => 1,
        'Data'      => ['RtnCode' => '1', 'InvoiceNo' => 'AB12345678'],
    ])->query('240101AAA-1');

    expect($result->successful)->toBeTrue()->and($result->invoiceNumber)->toBe('AB12345678');
});

it('checkLoveCode 存在 → true', function (): void {
    expect(invoiceGateway(['Data' => ['RtnCode' => 1, 'IsExist' => 'Y']])->checkLoveCode('123'))->toBeTrue();
});

it('checkLoveCode 不存在 → false', function (): void {
    expect(invoiceGateway(['Data' => ['RtnCode' => 1, 'IsExist' => 'N']])->checkLoveCode('123'))->toBeFalse();
});

it('checkBarcode 正確 → true', function (): void {
    expect(invoiceGateway(['Data' => ['RtnCode' => 1, 'IsExist' => 'Y']])->checkBarcode('/ABC123'))->toBeTrue();
});

it('companyName → 回公司名稱', function (): void {
    expect(invoiceGateway(['Data' => ['RtnCode' => 1, 'CompanyName' => '測試公司']])->companyName('12345678'))
        ->toBe('測試公司');
});

it('companyName 查無 → null', function (): void {
    expect(invoiceGateway(['Data' => ['RtnCode' => 0]])->companyName('12345678'))->toBeNull();
});
