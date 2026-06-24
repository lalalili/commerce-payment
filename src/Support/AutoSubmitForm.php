<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Support;

trait AutoSubmitForm
{
    /**
     * 產生會自動 submit 的隱藏表單 HTML（用於導向金流刷卡頁）。
     *
     * @param array<string, scalar|null> $parameters
     */
    protected function buildAutoSubmitHtml(string $url, array $parameters): string
    {
        $inputHtml = collect($parameters)
            ->map(function ($value, $key): string {
                $escapedKey = htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8');
                $escapedValue = htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

                return '<input type="hidden" name="' . $escapedKey . '" value="' . $escapedValue . '">';
            })
            ->implode('');

        return '<html><head><meta charset="utf-8"></head><body onload="document.forms[0].submit()"><form method="post" action="'
            . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            . '">' . $inputHtml . '</form></body></html>';
    }
}
