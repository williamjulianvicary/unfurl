<?php

declare(strict_types=1);

use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use WilliamJulianVicary\Unfurl\Jobs\GenerateOgImage;
use WilliamJulianVicary\Unfurl\Models\OgImage;
use WilliamJulianVicary\Unfurl\OgImageManager;

beforeEach(function (): void {
    Storage::fake('public');

    config()->set('unfurl.driver', 'cloudflare');
    config()->set('unfurl.storage.disk', 'public');
    config()->set('unfurl.storage.path', 'og-images');
    config()->set('unfurl.format', 'jpeg');
    config()->set('unfurl.width', 1200);
    config()->set('unfurl.height', 630);
    config()->set('unfurl.device_scale_factor', 2);
    config()->set('unfurl.drivers.cloudflare', [
        'account_id' => 'test-account-id',
        'api_token' => 'test-api-token',
    ]);
});

test('screenshots url and stores to disk', function (): void {
    Http::fake([
        'api.cloudflare.com/*' => Http::response('fake-jpeg-image'),
    ]);

    $job = new GenerateOgImage(key: 'homepage', url: 'https://example.com');
    $job->handle(app(OgImageManager::class));

    Storage::disk('public')->assertExists('og-images/homepage/default.jpeg');
    expect(Storage::disk('public')->get('og-images/homepage/default.jpeg'))->toBe('fake-jpeg-image');
});

test('creates database record', function (): void {
    Http::fake([
        'api.cloudflare.com/*' => Http::response('image-bytes'),
    ]);

    $job = new GenerateOgImage(key: 'homepage', url: 'https://example.com');
    $job->handle(app(OgImageManager::class));

    $ogImage = OgImage::query()->where('key', 'homepage')->first();

    expect($ogImage)->not->toBeNull()
        ->and($ogImage->variant)->toBe('default')
        ->and($ogImage->disk)->toBe('public')
        ->and($ogImage->path)->toBe('og-images/homepage/default.jpeg')
        ->and($ogImage->width)->toBe(1200)
        ->and($ogImage->height)->toBe(630);
});

test('uses variant dimensions', function (): void {
    config()->set('unfurl.variants', [
        'twitter' => ['width' => 1200, 'height' => 600],
    ]);

    Http::fake([
        'api.cloudflare.com/*' => Http::response('twitter-image'),
    ]);

    $job = new GenerateOgImage(key: 'homepage', url: 'https://example.com', variant: 'twitter');
    $job->handle(app(OgImageManager::class));

    $ogImage = OgImage::query()->where('key', 'homepage')->where('variant', 'twitter')->first();

    expect($ogImage->width)->toBe(1200)
        ->and($ogImage->height)->toBe(600);
});

test('upserts existing record on regeneration', function (): void {
    Http::fake([
        'api.cloudflare.com/*' => Http::response('updated-image'),
    ]);

    OgImage::query()->create([
        'key' => 'homepage',
        'variant' => 'default',
        'disk' => 'public',
        'path' => 'og-images/homepage/default.jpeg',
        'width' => 800,
        'height' => 400,
    ]);

    $job = new GenerateOgImage(key: 'homepage', url: 'https://example.com');
    $job->handle(app(OgImageManager::class));

    expect(OgImage::query()->where('key', 'homepage')->count())->toBe(1);
    expect(OgImage::query()->where('key', 'homepage')->first()->width)->toBe(1200);
});

test('unique id is key and variant', function (): void {
    $job = new GenerateOgImage(key: 'homepage', url: 'https://example.com', variant: 'twitter');

    expect($job->uniqueId())->toBe('homepage:twitter');
});

test('sends url to cloudflare', function (): void {
    Http::fake([
        'api.cloudflare.com/*' => Http::response('image-bytes'),
    ]);

    $job = new GenerateOgImage(key: 'test', url: 'https://example.com/my-page');
    $job->handle(app(OgImageManager::class));

    Http::assertSent(fn ($request): bool => $request->data()['url'] === 'https://example.com/my-page');
});

test('middleware includes WithoutOverlapping by default', function (): void {
    $job = new GenerateOgImage(key: 'homepage', url: 'https://example.com');

    $middleware = $job->middleware();

    expect(middlewareContains($middleware, WithoutOverlapping::class))->toBeTrue();
});

test('middleware excludes WithoutOverlapping when disabled', function (): void {
    config()->set('unfurl.queue.without_overlapping', false);

    $job = new GenerateOgImage(key: 'homepage', url: 'https://example.com');

    $middleware = $job->middleware();

    expect(middlewareContains($middleware, WithoutOverlapping::class))->toBeFalse();
});

test('middleware includes RateLimited by default', function (): void {
    $job = new GenerateOgImage(key: 'homepage', url: 'https://example.com');

    $middleware = $job->middleware();

    expect(middlewareContains($middleware, RateLimited::class))->toBeTrue();
});

test('middleware excludes RateLimited when disabled', function (): void {
    config()->set('unfurl.queue.rate_limit');

    $job = new GenerateOgImage(key: 'homepage', url: 'https://example.com');

    $middleware = $job->middleware();

    expect(middlewareContains($middleware, RateLimited::class))->toBeFalse();
});

function middlewareContains(array $middleware, string $class): bool
{
    return array_filter($middleware, fn ($m): bool => $m instanceof $class) !== [];
}
