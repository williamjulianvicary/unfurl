<?php

declare(strict_types=1);

namespace WilliamJulianVicary\Ogify;

use Illuminate\Support\Manager;
use WilliamJulianVicary\Ogify\Contracts\Renderer;
use WilliamJulianVicary\Ogify\Drivers\BrowsershotRenderer;
use WilliamJulianVicary\Ogify\Drivers\CloudflareRenderer;

final class OgImageManager extends Manager
{
    public function driver(mixed $driver = null): Renderer
    {
        $resolved = parent::driver($driver);

        if (! $resolved instanceof Renderer) {
            throw new \InvalidArgumentException(sprintf('Driver [%s] does not implement Renderer.', $driver ?? $this->getDefaultDriver()));
        }

        return $resolved;
    }

    public function getDefaultDriver(): string
    {
        return config()->string('og-image.driver', 'cloudflare');
    }

    public function createCloudflareDriver(): CloudflareRenderer
    {
        /** @var array{account_id: string|null, api_token: string|null} $config */
        $config = $this->config->get('og-image.drivers.cloudflare', []);

        return new CloudflareRenderer(
            accountId: $config['account_id'] ?? '',
            apiToken: $config['api_token'] ?? '',
            format: config()->string('og-image.format', 'jpeg'),
            deviceScaleFactor: config()->integer('og-image.device_scale_factor', 2),
        );
    }

    public function createBrowsershotDriver(): BrowsershotRenderer
    {
        /** @var array{node_binary: string|null, npm_binary: string|null, chrome_path: string|null} $config */
        $config = $this->config->get('og-image.drivers.browsershot', []);

        return new BrowsershotRenderer(
            nodeBinary: $config['node_binary'] ?? null,
            npmBinary: $config['npm_binary'] ?? null,
            chromePath: $config['chrome_path'] ?? null,
            format: config()->string('og-image.format', 'jpeg'),
            deviceScaleFactor: config()->integer('og-image.device_scale_factor', 2),
        );
    }
}
