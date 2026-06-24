<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Support;

class EcpayEndpointResolver
{
    private const STAGE_BASE = 'https://payment-stage.ecpay.com.tw';

    private const PROD_BASE = 'https://payment.ecpay.com.tw';

    private const INVOICE_STAGE_BASE = 'https://einvoice-stage.ecpay.com.tw';

    private const INVOICE_PROD_BASE = 'https://einvoice.ecpay.com.tw';

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
        return $this->isStage() ? self::STAGE_BASE : self::PROD_BASE;
    }

    public function invoiceBaseUrl(): string
    {
        return $this->isStage() ? self::INVOICE_STAGE_BASE : self::INVOICE_PROD_BASE;
    }

    private function isStage(): bool
    {
        return in_array($this->merchantId, $this->stageMerchantIds, true);
    }
}
