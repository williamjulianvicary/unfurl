<?php

declare(strict_types=1);

namespace WilliamJulianVicary\Ogify\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use WilliamJulianVicary\Ogify\Models\OgImage;
use WilliamJulianVicary\Ogify\OgImageManager;

final class GenerateOgImage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** @var int[] */
    public array $backoff = [1, 5, 10];

    public int $tries = 3;

    public function __construct(
        public string $key,
        public string $url,
        public string $variant = 'default',
    ) {
        $queueConfig = config('og-image.queue', []);

        if (is_array($queueConfig)) {
            if (isset($queueConfig['connection']) && is_string($queueConfig['connection'])) {
                $this->onConnection($queueConfig['connection']);
            }

            if (isset($queueConfig['name']) && is_string($queueConfig['name'])) {
                $this->onQueue($queueConfig['name']);
            }
        }
    }

    public function uniqueId(): string
    {
        return $this->key.':'.$this->variant;
    }

    public function handle(OgImageManager $manager): void
    {
        $dimensions = $this->resolveDimensions();

        $imageBytes = $manager->driver()->screenshot(
            $this->url,
            $dimensions['width'],
            $dimensions['height'],
        );

        $disk = config()->string('og-image.storage.disk', 'public');
        $basePath = config()->string('og-image.storage.path', 'og-images');
        $format = config()->string('og-image.format', 'jpeg');
        $filePath = sprintf('%s/%s/%s.%s', $basePath, $this->key, $this->variant, $format);

        Storage::disk($disk)->put($filePath, $imageBytes);

        OgImage::query()->updateOrCreate(
            ['key' => $this->key, 'variant' => $this->variant],
            [
                'disk' => $disk,
                'path' => $filePath,
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
            ],
        );
    }

    /**
     * @return array{width: int, height: int}
     */
    private function resolveDimensions(): array
    {
        if ($this->variant !== 'default') {
            /** @var array<string, array{width: int, height: int}> $variants */
            $variants = config('og-image.variants', []);

            if (isset($variants[$this->variant])) {
                return $variants[$this->variant];
            }
        }

        return [
            'width' => config()->integer('og-image.width', 1200),
            'height' => config()->integer('og-image.height', 630),
        ];
    }
}
