<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Data;

/**
 * 發起付款的結果（導向刷卡頁的方式）。host 可據此回傳 Response 或自行導向。
 */
class PaymentStartResult
{
    /**
     * @param array<string, scalar|null> $fields
     */
    public function __construct(
        public readonly string $mode,
        public readonly string $url,
        public readonly string $method = 'POST',
        public readonly array $fields = [],
        public readonly ?string $html = null,
    ) {
    }

    /**
     * @param array<string, scalar|null> $fields
     */
    public static function redirect(string $url, string $method = 'GET', array $fields = []): self
    {
        return new self('redirect', $url, strtoupper($method), $fields);
    }

    /**
     * @param array<string, scalar|null> $fields
     */
    public static function autoPostForm(string $url, array $fields, ?string $html = null): self
    {
        return new self('auto_post_form', $url, 'POST', $fields, $html);
    }

    public function isRedirectMode(): bool
    {
        return $this->mode === 'redirect';
    }

    public function isAutoPostMode(): bool
    {
        return $this->mode === 'auto_post_form';
    }
}
