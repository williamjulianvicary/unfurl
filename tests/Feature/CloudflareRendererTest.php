<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use WilliamJulianVicary\Ogify\Drivers\CloudflareRenderer;

test('sends url-based screenshot request to cloudflare', function (): void {
    Http::fake([
        'api.cloudflare.com/*' => Http::response('image-bytes'),
    ]);

    $renderer = new CloudflareRenderer(
        accountId: 'test-account',
        apiToken: 'test-token',
        format: 'jpeg',
        deviceScaleFactor: 2,
    );

    $result = $renderer->screenshot('https://example.com', 1200, 630);

    expect($result)->toBe('image-bytes');

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return str_contains((string) $request->url(), 'test-account/browser-rendering/screenshot')
            && $body['url'] === 'https://example.com'
            && $body['screenshotOptions']['type'] === 'jpeg'
            && $body['viewport']['deviceScaleFactor'] === 2
            && $body['viewport']['width'] === 1200
            && $body['viewport']['height'] === 630;
    });
});

test('throws on empty response', function (): void {
    Http::fake([
        'api.cloudflare.com/*' => Http::response(''),
    ]);

    $renderer = new CloudflareRenderer(
        accountId: 'test-account',
        apiToken: 'test-token',
    );

    $renderer->screenshot('https://example.com', 1200, 630);
})->throws(RuntimeException::class, 'Cloudflare Browser Rendering returned an empty response.');
