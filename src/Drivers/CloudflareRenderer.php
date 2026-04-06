<?php

declare(strict_types=1);

namespace WilliamJulianVicary\Ogify\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use WilliamJulianVicary\Ogify\Contracts\Renderer;

final readonly class CloudflareRenderer implements Renderer
{
    public function __construct(
        private string $accountId,
        private string $apiToken,
        private string $format = 'jpeg',
        private int $deviceScaleFactor = 2,
    ) {}

    /**
     * @throws ConnectionException
     * @throws RequestException
     */
    public function screenshot(string $url, int $width, int $height): string
    {
        $response = Http::withToken($this->apiToken)
            ->timeout(60)
            ->connectTimeout(10)
            ->retry(3, 1000)
            ->post(
                sprintf('https://api.cloudflare.com/client/v4/accounts/%s/browser-rendering/screenshot', $this->accountId),
                [
                    'url' => $url,
                    'type' => '.'.$this->format,
                    'deviceScaleFactor' => $this->deviceScaleFactor,
                    'viewport' => [
                        'width' => $width,
                        'height' => $height,
                    ],
                    'screenshotOptions' => [
                        'format' => $this->format,
                    ],
                    'gotoOptions' => [
                        'waitUntil' => 'networkidle0',
                    ],
                ],
            );

        $response->throw();

        $body = $response->body();

        if ($body === '') {
            throw new RuntimeException('Cloudflare Browser Rendering returned an empty response.');
        }

        return $body;
    }
}
