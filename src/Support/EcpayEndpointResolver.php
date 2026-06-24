<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Support;

class EcpayEndpointResolver
{
    private const STAGE_BASE = 'https://payment-stage.ecpay.com.tw';

    private const PROD_BASE = 'https://payment.ecpay.com.tw';

    /**
     * @param array<int, string> $stageMerchantIds
     */
    public function __construct(
        private readonly string $merchantId,
        private readonly array $stageMerchantIds = ['3002607', '3002599'],
    ) {
    }

    public function paymentBaseUrl(): string
    {
        return in_array($this->merchantId, $this->stageMerchantIds, true)
            ? self::STAGE_BASE
            : self::PROD_BASE;
    }
}
