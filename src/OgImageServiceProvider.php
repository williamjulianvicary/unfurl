<?php

declare(strict_types=1);

namespace WilliamJulianVicary\Unfurl;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use WilliamJulianVicary\Unfurl\Contracts\Renderer;
use WilliamJulianVicary\Unfurl\Http\Controllers\PreviewOgTemplateController;
use WilliamJulianVicary\Unfurl\Http\Controllers\RenderOgTemplateController;

final class OgImageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/unfurl.php', 'unfurl');

        $this->app->singleton(OgImageManager::class, fn (): OgImageManager => new OgImageManager($this->app));

        $this->app->bind(Renderer::class, fn (): Renderer => $this->app->make(OgImageManager::class)->driver());

        $this->app->singleton(OgImageService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'unfurl');

        $this->configureRateLimiting();

        if (config('unfurl.route.enabled', false)) {
            $this->registerRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/unfurl.php' => config_path('unfurl.php'),
            ], 'unfurl-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/unfurl'),
            ], 'unfurl-views');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'unfurl-migrations');
        }
    }

    private function configureRateLimiting(): void
    {
        $limit = config('unfurl.queue.rate_limit');

        if (is_int($limit) && $limit > 0) {
            RateLimiter::for('unfurl', fn (): Limit => Limit::perMinute($limit));
        }
    }

    private function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('unfurl.route.prefix', 'unfurl'),
            'middleware' => array_merge(['signed'], config()->array('unfurl.route.middleware', [])),
        ], function (): void {
            Route::get('render/{template}', RenderOgTemplateController::class)
                ->where('template', '.*')
                ->name('unfurl.render');

            Route::get('preview/{template}', PreviewOgTemplateController::class)
                ->where('template', '.*')
                ->name('unfurl.preview')
                ->withoutMiddleware('signed');
        });
    }
}
