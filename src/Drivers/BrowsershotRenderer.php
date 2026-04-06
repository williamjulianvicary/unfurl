<?php

declare(strict_types=1);

namespace WilliamJulianVicary\Ogify\Drivers;

use RuntimeException;
use Spatie\Browsershot\Browsershot;
use WilliamJulianVicary\Ogify\Contracts\Renderer;

final readonly class BrowsershotRenderer implements Renderer
{
    public function __construct(
        private ?string $nodeBinary = null,
        private ?string $npmBinary = null,
        private ?string $chromePath = null,
        private string $format = 'jpeg',
        private int $deviceScaleFactor = 2,
    ) {}

    public function screenshot(string $url, int $width, int $height): string
    {
        if (! class_exists(Browsershot::class)) {
            throw new RuntimeException(
                'The spatie/browsershot package is required for the Browsershot driver. Install it with: composer require spatie/browsershot',
            );
        }

        $browsershot = Browsershot::url($url)
            ->windowSize($width, $height)
            ->deviceScaleFactor($this->deviceScaleFactor);

        if ($this->nodeBinary !== null) {
            $browsershot->setNodeBinary($this->nodeBinary);
        }

        if ($this->npmBinary !== null) {
            $browsershot->setNpmBinary($this->npmBinary);
        }

        if ($this->chromePath !== null) {
            $browsershot->setChromePath($this->chromePath);
        }

        if ($this->format === 'png') {
            return $browsershot->screenshot();
        }

        return $browsershot->setScreenshotType('jpeg', 90)->screenshot();
    }
}
