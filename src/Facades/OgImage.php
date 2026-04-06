<?php

declare(strict_types=1);

namespace WilliamJulianVicary\Ogify\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use WilliamJulianVicary\Ogify\OgImageBuilder;
use WilliamJulianVicary\Ogify\OgImageService;

/**
 * @method static OgImageBuilder for(string|Model $subject)
 * @method static string keyForModel(Model $model)
 *
 * @see OgImageService
 */
final class OgImage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OgImageService::class;
    }
}
