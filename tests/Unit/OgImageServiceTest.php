<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use WilliamJulianVicary\Ogify\OgImageBuilder;
use WilliamJulianVicary\Ogify\OgImageService;

test('for with string key returns builder', function (): void {
    $service = app(OgImageService::class);

    expect($service->for('homepage'))->toBeInstanceOf(OgImageBuilder::class);
});

test('for with model returns builder with hashed key', function (): void {
    $model = new class extends Model
    {
        protected $table = 'articles';
    };
    $model->id = 1;

    $key = OgImageService::keyForModel($model);

    expect($key)->toBe(hash('xxh128', $model->getMorphClass().':1'))
        ->and(mb_strlen($key))->toBe(32);
});

test('keyForModel returns consistent hash', function (): void {
    $model = new class extends Model
    {
        protected $table = 'articles';
    };
    $model->id = 42;

    $key1 = OgImageService::keyForModel($model);
    $key2 = OgImageService::keyForModel($model);

    expect($key1)->toBe($key2);
});
