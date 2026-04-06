<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use WilliamJulianVicary\Ogify\Jobs\GenerateOgImage;
use WilliamJulianVicary\Ogify\Models\OgImage;
use WilliamJulianVicary\Ogify\OgImageManager;

beforeEach(function (): void {
    Storage::fake('public');

    config()->set('og-image.driver', 'cloudflare');
    config()->set('og-image.storage.disk', 'public');
    config()->set('og-image.storage.path', 'og-images');
    config()->set('og-image.format', 'jpeg');
    config()->set('og-image.width', 1200);
    config()->set('og-image.height', 630);
    config()->set('og-image.device_scale_factor', 2);
    config()->set('og-image.drivers.cloudflare', [
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
    config()->set('og-image.variants', [
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
