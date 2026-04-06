<?php

declare(strict_types=1);

namespace WilliamJulianVicary\Ogify;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use WilliamJulianVicary\Ogify\Jobs\GenerateOgImage;
use WilliamJulianVicary\Ogify\Models\OgImage;

final class OgImageBuilder
{
    private ?string $sourceUrl = null;

    private string $variant = 'default';

    public function __construct(
        private readonly string $key,
        private readonly OgImageService $service,
    ) {}

    /**
     * Set the source as a URL to screenshot directly.
     */
    public function screenshot(string $url): self
    {
        $this->sourceUrl = $url;

        return $this;
    }

    /**
     * Set the source as a Blade template rendered via the package's signed route.
     *
     * Requires the template render route to be enabled in config (og-image.route.enabled).
     *
     * @param  array<string, mixed>  $params
     */
    public function template(string $view, array $params = []): self
    {
        if (! config('og-image.route.enabled', false)) {
            throw new RuntimeException(
                'The OG image template route is not enabled. Set "route.enabled" to true in your og-image config.',
            );
        }

        $this->sourceUrl = URL::signedRoute('og-image.render', [
            'template' => $view,
            'params' => base64_encode(json_encode($params, JSON_THROW_ON_ERROR)),
        ]);

        return $this;
    }

    /**
     * Set the variant (e.g. 'twitter', 'square').
     */
    public function variant(string $variant): self
    {
        $this->variant = $variant;

        return $this;
    }

    /**
     * Get the stored URL for this OG image.
     *
     * If no image exists and a source has been set, dispatches generation
     * when generate_on_access is enabled and returns the expected URL.
     */
    public function url(?string $variant = null): ?string
    {
        $variant ??= $this->variant;

        $ogImage = OgImage::query()
            ->where('key', $this->key)
            ->where('variant', $variant)
            ->first();

        if ($ogImage instanceof OgImage) {
            $this->refreshIfStale($ogImage);

            return $ogImage->url();
        }

        if (config('og-image.generate_on_access', true)) {
            if ($this->sourceUrl === null && app()->runningInConsole()) {
                return null;
            }

            $this->sourceUrl ??= $this->resolveCurrentUrl();
            $this->service->dispatch(new GenerateOgImage(
                key: $this->key,
                url: $this->sourceUrl,
                variant: $variant,
            ));

            return $this->expectedUrl($variant);
        }

        return null;
    }

    /**
     * Dispatch a regeneration job if the image is older than the configured threshold.
     */
    private function refreshIfStale(OgImage $ogImage): void
    {
        $days = config('og-image.refresh_after_days');

        if ($days === null) {
            return;
        }

        if ($ogImage->updated_at >= Carbon::now()->subDays(config()->integer('og-image.refresh_after_days', 30))) {
            return;
        }

        if ($this->sourceUrl === null && app()->runningInConsole()) {
            return;
        }

        $this->sourceUrl ??= $this->resolveCurrentUrl();
        $this->service->dispatch(new GenerateOgImage(
            key: $this->key,
            url: $this->sourceUrl,
            variant: $ogImage->variant,
        ));
    }

    /**
     * Force generation and return the expected storage URL.
     */
    public function generate(?string $variant = null): string
    {
        $variant ??= $this->variant;

        $this->sourceUrl ??= $this->resolveCurrentUrl();

        $this->service->dispatch(new GenerateOgImage(
            key: $this->key,
            url: $this->sourceUrl,
            variant: $variant,
        ));

        return $this->expectedUrl($variant);
    }

    /**
     * Render the screenshot synchronously and return the raw image bytes.
     *
     * Useful for debugging, streaming responses, or custom storage logic.
     */
    public function render(?string $variant = null): string
    {
        $variant ??= $this->variant;

        $this->sourceUrl ??= $this->resolveCurrentUrl();

        $dimensions = $this->resolveDimensions($variant);

        return app(OgImageManager::class)->driver()->screenshot(
            $this->sourceUrl,
            $dimensions['width'],
            $dimensions['height'],
        );
    }

    /**
     * Get the source URL that the driver will screenshot.
     *
     * Useful for previewing what the screenshot will capture.
     * For templates, this returns the signed render route URL.
     */
    public function preview(): string
    {
        $this->sourceUrl ??= $this->resolveCurrentUrl();

        return $this->sourceUrl;
    }

    /**
     * Delete all OG images for this key from storage and the database.
     */
    public function delete(): void
    {
        OgImage::query()
            ->where('key', $this->key)
            ->get()
            ->each(function (OgImage $ogImage): void {
                Storage::disk($ogImage->disk)->delete($ogImage->path);
                $ogImage->delete();
            });
    }

    /**
     * Resolve the current request URL, or throw if not in an HTTP context.
     */
    private function resolveCurrentUrl(): string
    {
        if (app()->runningInConsole()) {
            throw new RuntimeException(
                'Cannot generate an OG image without a source URL in a CLI context. Call screenshot() or template() first.',
            );
        }

        return url()->current();
    }

    /**
     * @return array{width: int, height: int}
     */
    private function resolveDimensions(string $variant): array
    {
        if ($variant !== 'default') {
            /** @var array<string, array{width: int, height: int}> $variants */
            $variants = config('og-image.variants', []);

            if (isset($variants[$variant])) {
                return $variants[$variant];
            }
        }

        return [
            'width' => config()->integer('og-image.width', 1200),
            'height' => config()->integer('og-image.height', 630),
        ];
    }

    /**
     * Compute the deterministic URL before the image has been generated.
     */
    private function expectedUrl(string $variant): string
    {
        $disk = config()->string('og-image.storage.disk', 'public');
        $basePath = config()->string('og-image.storage.path', 'og-images');
        $format = config()->string('og-image.format', 'jpeg');

        $path = sprintf('%s/%s/%s.%s', $basePath, $this->key, $variant, $format);
        $filesystem = Storage::disk($disk);

        if ($filesystem instanceof FilesystemAdapter) {
            return $filesystem->url($path);
        }

        return $path;
    }
}
