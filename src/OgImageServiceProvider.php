<?php

declare(strict_types=1);

namespace WilliamJulianVicary\Ogify;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use WilliamJulianVicary\Ogify\Contracts\Renderer;
use WilliamJulianVicary\Ogify\Http\Controllers\PreviewOgTemplateController;
use WilliamJulianVicary\Ogify\Http\Controllers\RenderOgTemplateController;

final class OgImageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/og-image.php', 'og-image');

        $this->app->singleton(OgImageManager::class, fn (): OgImageManager => new OgImageManager($this->app));

        $this->app->bind(Renderer::class, fn (): Renderer => $this->app->make(OgImageManager::class)->driver());

        $this->app->singleton(OgImageService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'og-image');

        if (config('og-image.route.enabled', false)) {
            $this->registerRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/og-image.php' => config_path('og-image.php'),
            ], 'og-image-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/og-image'),
            ], 'og-image-views');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'og-image-migrations');
        }
    }

    private function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('og-image.route.prefix', 'og-image'),
            'middleware' => array_merge(['signed'], config()->array('og-image.route.middleware', [])),
        ], function (): void {
            Route::get('render/{template}', RenderOgTemplateController::class)
                ->where('template', '.*')
                ->name('og-image.render');

            Route::get('preview/{template}', PreviewOgTemplateController::class)
                ->where('template', '.*')
                ->name('og-image.preview')
                ->withoutMiddleware('signed');
        });
    }
}
