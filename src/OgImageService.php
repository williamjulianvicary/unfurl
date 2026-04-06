<?php

declare(strict_types=1);

namespace WilliamJulianVicary\Ogify;

use Illuminate\Database\Eloquent\Model;
use WilliamJulianVicary\Ogify\Jobs\GenerateOgImage;

final readonly class OgImageService
{
    /**
     * Derive a deterministic key from a model.
     */
    public static function keyForModel(Model $model): string
    {
        $key = $model->getKey();

        return hash('xxh128', $model->getMorphClass().':'.(is_scalar($key) ? strval($key) : ''));
    }

    /**
     * Start building an OG image operation for a given key or model.
     */
    public function for(string|Model $subject): OgImageBuilder
    {
        $key = $subject instanceof Model
            ? self::keyForModel($subject)
            : $subject;

        return new OgImageBuilder($key, $this);
    }

    /**
     * Dispatch a generation job respecting queue configuration.
     */
    public function dispatch(GenerateOgImage $job): void
    {
        if (config('og-image.queue.enabled', true)) {
            dispatch($job);
        } else {
            dispatch_sync($job);
        }
    }
}
