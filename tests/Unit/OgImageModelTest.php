<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use WilliamJulianVicary\Ogify\Models\OgImage;

test('url returns storage url for the image', function (): void {
    Storage::fake('public');

    $ogImage = new OgImage;
    $ogImage->forceFill([
        'disk' => 'public',
        'path' => 'og-images/abc123/default.jpeg',
        'width' => 1200,
        'height' => 630,
    ]);

    expect($ogImage->url())->toContain('og-images/abc123/default.jpeg');
});

test('fresh scope excludes stale images', function (): void {
    config()->set('og-image.refresh_after_days', 30);

    (new OgImage)->forceFill([
        'key' => 'stale',
        'variant' => 'default',
        'disk' => 'public',
        'path' => 'og-images/stale/default.jpeg',
        'width' => 1200,
        'height' => 630,
        'updated_at' => now()->subDays(31),
    ])->save();

    (new OgImage)->forceFill([
        'key' => 'recent',
        'variant' => 'default',
        'disk' => 'public',
        'path' => 'og-images/recent/default.jpeg',
        'width' => 1200,
        'height' => 630,
    ])->save();

    $fresh = OgImage::query()->fresh()->pluck('key')->all();

    expect($fresh)->toBe(['recent']);
});

test('fresh scope includes all when refresh is disabled', function (): void {
    config()->set('og-image.refresh_after_days');

    (new OgImage)->forceFill([
        'key' => 'old',
        'variant' => 'default',
        'disk' => 'public',
        'path' => 'og-images/old/default.jpeg',
        'width' => 1200,
        'height' => 630,
        'updated_at' => now()->subYear(),
    ])->save();

    expect(OgImage::query()->fresh()->count())->toBe(1);
});

test('casts width and height to integers', function (): void {
    $ogImage = new OgImage;
    $ogImage->forceFill([
        'width' => '1200',
        'height' => '630',
    ]);

    expect($ogImage->width)->toBeInt()
        ->and($ogImage->height)->toBeInt();
});
