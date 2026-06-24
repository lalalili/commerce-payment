<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Support;

use GuzzleHttp\Client;

trait InteractsWithHttp
{
    /**
     * 送出 HTTP 請求並回傳 body 字串。
     *
     * @param array<string, mixed> $parameters
     * @param array<string, mixed>|null $extraOptions
     */
    public function sendGuzzleRequest(
        string $method,
        string $url,
        array $parameters = [],
        bool $isJson = false,
        int $timeout = 10,
        ?array $extraOptions = null,
    ): string {
        $client = new Client();
        $options = [
            'timeout' => $timeout,
            'verify'  => false,
        ];

        if (! empty($parameters)) {
            if ($method === 'POST') {
                $options[$isJson ? 'json' : 'form_params'] = $parameters;
            } else {
                $options['query'] = $parameters;
            }
        }

        if ($extraOptions) {
            $options = array_merge($options, $extraOptions);
        }

        return $client->request($method, $url, $options)->getBody()->getContents();
    }
}
