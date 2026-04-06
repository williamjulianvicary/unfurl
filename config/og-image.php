<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    |
    | The default driver used for rendering OG images. Supported drivers:
    | "cloudflare", "browsershot"
    |
    */

    'driver' => env('OG_IMAGE_DRIVER', 'cloudflare'),

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    |
    | Configuration for each rendering driver.
    |
    */

    'drivers' => [

        'cloudflare' => [
            'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
            'api_token' => env('CLOUDFLARE_BROWSER_RENDERING_TOKEN'),
        ],

        'browsershot' => [
            'node_binary' => env('OG_IMAGE_NODE_BINARY'),
            'npm_binary' => env('OG_IMAGE_NPM_BINARY'),
            'chrome_path' => env('OG_IMAGE_CHROME_PATH'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | The disk and folder used to persist generated OG images.
    |
    */

    'storage' => [
        'disk' => env('OG_IMAGE_DISK', 'public'),
        'path' => env('OG_IMAGE_PATH', 'og-images'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Dimensions
    |--------------------------------------------------------------------------
    |
    | Default width and height for generated OG images.
    |
    */

    'width' => 1200,
    'height' => 630,

    /*
    |--------------------------------------------------------------------------
    | Variants
    |--------------------------------------------------------------------------
    |
    | Define named variants with custom dimensions. The "default" variant
    | uses the width/height values above. Additional variants can be
    | defined here for different social platforms or use cases.
    |
    */

    'variants' => [
        // 'twitter' => ['width' => 1200, 'height' => 600],
        // 'square' => ['width' => 1200, 'height' => 1200],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Configure how OG image generation jobs are dispatched.
    |
    */

    'queue' => [
        'enabled' => true,
        'connection' => env('OG_IMAGE_QUEUE_CONNECTION'),
        'name' => env('OG_IMAGE_QUEUE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Generate On Access
    |--------------------------------------------------------------------------
    |
    | When enabled, calling url() on the builder with a source set will
    | automatically dispatch a generation job if no image exists yet,
    | and return the expected URL.
    |
    */

    'generate_on_access' => true,

    /*
    |--------------------------------------------------------------------------
    | Refresh After (Days)
    |--------------------------------------------------------------------------
    |
    | Automatically regenerate OG images that are older than this many days.
    | When url() finds an existing image whose updated_at timestamp is older
    | than this threshold, it will dispatch a regeneration job in the
    | background and return the existing URL in the meantime.
    |
    | Set to null to disable automatic refresh entirely.
    |
    */

    'refresh_after_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Image Format
    |--------------------------------------------------------------------------
    |
    | The output format for generated images. Supported: "jpeg", "png"
    |
    */

    'format' => 'jpeg',

    /*
    |--------------------------------------------------------------------------
    | Device Scale Factor
    |--------------------------------------------------------------------------
    |
    | The device scale factor for rendering. Use 2 for retina-quality images.
    |
    */

    'device_scale_factor' => 2,

    /*
    |--------------------------------------------------------------------------
    | Template Prefix
    |--------------------------------------------------------------------------
    |
    | The view namespace prefix used when resolving short template names.
    | For example, template('basic') resolves to 'og-image::templates.basic'.
    | Change this to use your own published or custom view directory.
    |
    */

    'template_prefix' => 'og-image::templates',

    /*
    |--------------------------------------------------------------------------
    | Template Render Route
    |--------------------------------------------------------------------------
    |
    | Enable this to register a route that renders Blade templates for
    | screenshot. Required when using the template() strategy. The route
    | is always protected by signed middleware to prevent direct access.
    |
    */

    'route' => [
        'enabled' => false,
        'prefix' => 'og-image',
        'middleware' => [],
    ],

];
