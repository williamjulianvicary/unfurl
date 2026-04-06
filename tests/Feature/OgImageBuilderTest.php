<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use WilliamJulianVicary\Ogify\Facades\OgImage;
use WilliamJulianVicary\Ogify\Jobs\GenerateOgImage;
use WilliamJulianVicary\Ogify\Models\OgImage as OgImageModel;

beforeEach(function (): void {
    Storage::fake('public');

    config()->set('og-image.driver', 'cloudflare');
    config()->set('og-image.storage.disk', 'public');
    config()->set('og-image.storage.path', 'og-images');
    config()->set('og-image.format', 'jpeg');
    config()->set('og-image.queue.enabled', true);
    config()->set('og-image.generate_on_access', true);
    config()->set('og-image.drivers.cloudflare', [
        'account_id' => 'test-account-id',
        'api_token' => 'test-api-token',
    ]);
});

test('generate dispatches job and returns expected url', function (): void {
    Bus::fake();

    $url = OgImage::for('homepage')->screenshot('https://example.com')->generate();

    Bus::assertDispatched(GenerateOgImage::class, fn (GenerateOgImage $job): bool => $job->key === 'homepage'
        && $job->url === 'https://example.com'
        && $job->variant === 'default');

    expect($url)->toContain('og-images/homepage/default.jpeg');
});

test('generate with variant', function (): void {
    Bus::fake();

    $url = OgImage::for('homepage')->screenshot('https://example.com')->variant('twitter')->generate();

    Bus::assertDispatched(GenerateOgImage::class, fn (GenerateOgImage $job): bool => $job->variant === 'twitter');

    expect($url)->toContain('og-images/homepage/twitter.jpeg');
});

test('generate throws without source', function (): void {
    OgImage::for('homepage')->generate();
})->throws(RuntimeException::class, 'Cannot generate an OG image without a source');

test('url returns stored url when image exists', function (): void {
    OgImageModel::query()->create([
        'key' => 'homepage',
        'variant' => 'default',
        'disk' => 'public',
        'path' => 'og-images/homepage/default.jpeg',
        'width' => 1200,
        'height' => 630,
    ]);

    $url = OgImage::for('homepage')->url();

    expect($url)->toContain('og-images/homepage/default.jpeg');
});

test('url returns null when no image and no source', function (): void {
    $url = OgImage::for('homepage')->url();

    expect($url)->toBeNull();
});

test('url dispatches and returns expected url when source set and generate on access enabled', function (): void {
    Bus::fake();

    $url = OgImage::for('homepage')->screenshot('https://example.com')->url();

    Bus::assertDispatched(GenerateOgImage::class);
    expect($url)->toContain('og-images/homepage/default.jpeg');
});

test('url does not dispatch when generate on access disabled', function (): void {
    Bus::fake();
    config()->set('og-image.generate_on_access', false);

    $url = OgImage::for('homepage')->screenshot('https://example.com')->url();

    Bus::assertNotDispatched(GenerateOgImage::class);
    expect($url)->toBeNull();
});

test('template builds signed url and dispatches', function (): void {
    Bus::fake();

    $url = OgImage::for('homepage')->template('og-test', ['title' => 'Hello'])->generate();

    Bus::assertDispatched(GenerateOgImage::class, fn (GenerateOgImage $job): bool => $job->key === 'homepage'
        && str_contains($job->url, 'og-image/render/og-test')
        && str_contains($job->url, 'signature='));

    expect($url)->toContain('og-images/homepage/default.jpeg');
});

test('for with model derives hashed key', function (): void {
    Bus::fake();

    $model = new class extends Model
    {
        protected $table = 'articles';
    };
    $model->id = 1;

    $expectedKey = hash('xxh128', $model->getMorphClass().':1');

    OgImage::for($model)->screenshot('https://example.com')->generate();

    Bus::assertDispatched(GenerateOgImage::class, fn (GenerateOgImage $job): bool => $job->key === $expectedKey);
});

test('delete removes images from storage and database', function (): void {
    Storage::disk('public')->put('og-images/homepage/default.jpeg', 'content');

    OgImageModel::query()->create([
        'key' => 'homepage',
        'variant' => 'default',
        'disk' => 'public',
        'path' => 'og-images/homepage/default.jpeg',
        'width' => 1200,
        'height' => 630,
    ]);

    OgImage::for('homepage')->delete();

    Storage::disk('public')->assertMissing('og-images/homepage/default.jpeg');
    expect(OgImageModel::query()->where('key', 'homepage')->count())->toBe(0);
});

test('generate dispatches synchronously when queue disabled', function (): void {
    Bus::fake();
    config()->set('og-image.queue.enabled', false);

    OgImage::for('homepage')->screenshot('https://example.com')->generate();

    Bus::assertDispatchedSync(GenerateOgImage::class);
});

test('url dispatches refresh when image is stale', function (): void {
    Bus::fake();
    config()->set('og-image.refresh_after_days', 30);

    (new OgImageModel)->forceFill([
        'key' => 'homepage',
        'variant' => 'default',
        'disk' => 'public',
        'path' => 'og-images/homepage/default.jpeg',
        'width' => 1200,
        'height' => 630,
        'updated_at' => now()->subDays(31),
    ])->save();

    $url = OgImage::for('homepage')->screenshot('https://example.com')->url();

    expect($url)->toContain('og-images/homepage/default.jpeg');
    Bus::assertDispatched(GenerateOgImage::class, fn (GenerateOgImage $job): bool => $job->key === 'homepage'
        && $job->variant === 'default');
});

test('url does not dispatch refresh when image is fresh', function (): void {
    Bus::fake();
    config()->set('og-image.refresh_after_days', 30);

    (new OgImageModel)->forceFill([
        'key' => 'homepage',
        'variant' => 'default',
        'disk' => 'public',
        'path' => 'og-images/homepage/default.jpeg',
        'width' => 1200,
        'height' => 630,
        'updated_at' => now()->subDays(10),
    ])->save();

    $url = OgImage::for('homepage')->screenshot('https://example.com')->url();

    expect($url)->toContain('og-images/homepage/default.jpeg');
    Bus::assertNotDispatched(GenerateOgImage::class);
});

test('url does not dispatch refresh when refresh is disabled', function (): void {
    Bus::fake();
    config()->set('og-image.refresh_after_days');

    (new OgImageModel)->forceFill([
        'key' => 'homepage',
        'variant' => 'default',
        'disk' => 'public',
        'path' => 'og-images/homepage/default.jpeg',
        'width' => 1200,
        'height' => 630,
        'updated_at' => now()->subDays(365),
    ])->save();

    $url = OgImage::for('homepage')->screenshot('https://example.com')->url();

    expect($url)->toContain('og-images/homepage/default.jpeg');
    Bus::assertNotDispatched(GenerateOgImage::class);
});

test('url dispatches refresh at exact boundary', function (): void {
    Bus::fake();
    config()->set('og-image.refresh_after_days', 30);

    (new OgImageModel)->forceFill([
        'key' => 'homepage',
        'variant' => 'default',
        'disk' => 'public',
        'path' => 'og-images/homepage/default.jpeg',
        'width' => 1200,
        'height' => 630,
        'updated_at' => now()->subDays(30)->subSecond(),
    ])->save();

    $url = OgImage::for('homepage')->screenshot('https://example.com')->url();

    expect($url)->toContain('og-images/homepage/default.jpeg');
    Bus::assertDispatched(GenerateOgImage::class);
});

test('url accepts variant shorthand', function (): void {
    OgImageModel::query()->create([
        'key' => 'homepage',
        'variant' => 'twitter',
        'disk' => 'public',
        'path' => 'og-images/homepage/twitter.jpeg',
        'width' => 1200,
        'height' => 600,
    ]);

    $url = OgImage::for('homepage')->url('twitter');

    expect($url)->toContain('og-images/homepage/twitter.jpeg');
});
