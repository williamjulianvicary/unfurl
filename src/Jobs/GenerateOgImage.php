<?php

declare(strict_types=1);

namespace WilliamJulianVicary\Unfurl\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use WilliamJulianVicary\Unfurl\Models\OgImage;
use WilliamJulianVicary\Unfurl\OgImageManager;

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
        $queueConfig = config('unfurl.queue', []);

        if (is_array($queueConfig)) {
            if (isset($queueConfig['connection']) && is_string($queueConfig['connection'])) {
                $this->onConnection($queueConfig['connection']);
            }

            if (isset($queueConfig['name']) && is_string($queueConfig['name'])) {
                $this->onQueue($queueConfig['name']);
            }
        }
    }

    /** @return list<object> */
    public function middleware(): array
    {
        $middleware = [];

        if (config('unfurl.queue.without_overlapping', true)) {
            $middleware[] = new WithoutOverlapping($this->uniqueId());
        }

        if (config('unfurl.queue.rate_limit')) {
            $middleware[] = new RateLimited('unfurl');
        }

        return $middleware;
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

        $disk = config()->string('unfurl.storage.disk', 'public');
        $basePath = config()->string('unfurl.storage.path', 'og-images');
        $format = config()->string('unfurl.format', 'jpeg');
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
            $variants = config('unfurl.variants', []);

            if (isset($variants[$this->variant])) {
                return $variants[$this->variant];
            }
        }

        return [
            'width' => config()->integer('unfurl.width', 1200),
            'height' => config()->integer('unfurl.height', 630),
        ];
    }
}
