<?php

declare(strict_types=1);

namespace WilliamJulianVicary\Ogify\Contracts;

interface Renderer
{
    /**
     * Take a screenshot of a URL and return raw image bytes.
     */
    public function screenshot(string $url, int $width, int $height): string;
}
