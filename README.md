# Unfurl for Laravel - Modern OG image generation for URLs or via templates.

[![Tests](https://github.com/williamjulianvicary/unfurl/actions/workflows/tests.yml/badge.svg)](https://github.com/williamjulianvicary/unfurl/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/williamjulianvicary/unfurl)](https://packagist.org/packages/williamjulianvicary/unfurl)
[![License](https://img.shields.io/packagist/l/williamjulianvicary/unfurl)](https://packagist.org/packages/williamjulianvicary/unfurl)

Most OG image packages for Laravel assume you have Browsershot (and therefore a local Node/Puppeteer install) available - that's a non-starter on managed platforms like Laravel Cloud. They also tend to serve images through PHP on every request and offer limited templating.

Unfurl takes a different approach:

- **Driver-based rendering** - ship with Cloudflare Browser Rendering (no server-side browser needed) or fall back to Browsershot when you can.
- **Static file serving** - generated images are stored on any Laravel filesystem disk (public by default) and served directly by your web server or CDN, not through PHP.
- **Queue-first generation** - images are rendered in the background via Laravel's queue so page loads are never blocked.
- **Built-in templates** - includes ready-to-use Blade templates (`basic`, `dark`, `minimal`) with automatic text fitting - customise the templates or bring your own.

> **Requires [PHP 8.2+](https://php.net/releases/)** and **[Laravel 11+](https://laravel.com)** and either **CloudFlare Browser Rendering (free tier available) or Browsershot** with local Chrome available.

- [Installation](#installation)
- [Quickstart](#quickstart)
- [How it works](#how-it-works)
- [Usage](#usage)
  - [Setting the source](#setting-the-source)
  - [`url()`, `generate()`, and `render()`](#url-generate-and-render)
  - [Automatic refresh](#automatic-refresh)
  - [Variants](#variants)
  - [Deleting images](#deleting-images)
  - [Working with models](#working-with-models)
- [Drivers](#drivers)
- [Configuration](#configuration)

## Installation

```bash
composer require williamjulianvicary/unfurl
```

**Publish the config, migrations and blade OG image templates:**

```bash
php artisan vendor:publish --provider="WilliamJulianVicary\Unfurl\OgImageServiceProvider"
php artisan migrate
```

**Configure your drivers:**

* **Cloudflare**, add your .env variables with your Cloudflare details (see [before you begin](https://developers.cloudflare.com/browser-rendering/rest-api/#before-you-begin)):
```
CLOUDFLARE_ACCOUNT_ID=
CLOUDFLARE_BROWSER_RENDERING_TOKEN=
```
* **Browsershot** (when local Chrome rendering is practical):
Update driver to use Browsershot in your .env: `UNFURL_DRIVER=browsershot` and install browsershot:
```
composer require spatie/browsershot
```

**Optionally, enable routes for rendering page templates:**
Templates are rendered as blade files with parameters passed to these URLs to render the OG image.

By default these routes are NOT registered.

To register the routes for templates publish the config file and then adjust `/config/unfurl.php`:
```
'route' => [
        'enabled' => true, // adjust this from false -> true
        'prefix' => 'unfurl', // optionally adjust the path that images are generated from.
        'middleware' => [],
],
```


## Quickstart
Complete installation and then the below is all you need to set up a simple OG image which takes a screenshot of your current URL (without query parameters), this returns the _expected_ OG image URL that you can pass to your views/frontend to render - image creation is then handled asynchronously via the Laravel queue:

```
OgImage::for('<unique key>')->screenshot()->url();
// Or for a model, for the current URL:
OgImage::for($model)->screenshot()->url();
```

By default this makes the following assumptions (configurable in the `/config/unfurl.php` once published):

* You have a queue worker running and queues will be dispatched for async generation
* Images are stored and served from the `public` disk

For template based OG image examples, keep reading.

## How it works

1. You define a URL to screenshot or a Blade template to render (templates are included)
    1. For URL based rendering, the URL is loaded by the service at a relevant viewport.
    2. For template based rendering, a URL is passed to the driver (default: `/unfurl/render/{template}`) to render the template.
2. Unfurl dispatches a queued job that uses a **driver** (Cloudflare Browser Rendering, the default or Browsershot) to take a screenshot of that source URL - for templates this is via a Signed URL for security.
    1. The queued jobs implements `ShouldBeUnique` to block excessive requests.
3. The image is stored on the Laravel filesystem disk (public by default, configurable in the config) and tracked in the database with a deterministic key.
4. On subsequent requests, `url()` returns the stored image URL instantly - no re-rendering.

When `generate_on_access` is enabled (the default), the first call to `url()` will automatically dispatch generation in the background and return the expected URL, so images are created lazily without blocking your response.

## Usage

Every operation starts with `OgImage::for()`, which accepts a string key or an Eloquent model (a deterministic key is derived automatically from the model).

```php
use WilliamJulianVicary\Unfurl\Facades\OgImage;
```

### Setting the source

You can set the source as a URL to screenshot or a Blade template to render.

**Screenshot a URL:**

```php
OgImage::for('my-page')->screenshot('https://example.com')->url();
```

**Render from a Blade template:**

To use templates, first enable the render route in `config/unfurl.php`:

```php
'route' => [
    'enabled' => true,
],
```

Use one of the built-in templates (`basic`, `dark`, `minimal`) or your own. **All parameters are optional**:

```php
OgImage::for($post)->template('basic', [
    'title'       => 'My Blog Post',
    'description' => 'A short summary of the post.',
    'author'      => 'Jane Doe',
    'avatar'      => 'https://example.com/avatar.jpg',
    'date'        => 'April 6, 2026',
    'domain'      => 'example.com',
    'accent'      => '#667eea',
])->url();
```

| Parameter     | Description                                      |
|---------------|--------------------------------------------------|
| `title`       | Main heading text                                |
| `description` | Secondary text below the title                   |
| `author`      | Author name displayed in the footer              |
| `avatar`      | URL to an avatar image (rendered as a circle)    |
| `date`        | Date string displayed alongside the author       |
| `domain`      | Domain or site name displayed in the footer      |
| `accent`      | Accent colour for borders and decorative elements |

### `url()`, `generate()`, and `render()`

The builder provides three ways to produce an image:

| Method       | Dispatches job | Returns                  | Use case                                                  |
|--------------|----------------|--------------------------|-----------------------------------------------------------|
| `url()`      | Lazily         | `?string` - the image URL | **Most common.** Returns the stored URL if the image exists. When `generate_on_access` is enabled (the default), dispatches a background job on first access and returns the expected URL. Regenerates every 30 days by default (configurable) |
| `generate()` | Always         | `string` - the expected URL | Forces a (re)generation job to be dispatched and returns the expected URL. Useful for seeding or regenerating images. |
| `render()`   | No             | `string` - raw image bytes | Renders the screenshot synchronously in-process. No job, no storage. Useful for streaming responses or custom storage logic. |

**Typical usage - `url()` in a Blade view:**

```html
<meta property="og:image" content="{{ OgImage::for($post)->template('basic', ['title' => $post->title])->url() }}">
```

**Force regeneration with `generate()`:**

```php
// e.g. in an observer or artisan command
OgImage::for($post)->template('basic', ['title' => $post->title])->generate();
```

**Stream raw bytes with `render()`:**

```php
$bytes = OgImage::for($post)->screenshot('https://example.com')->render();

return response($bytes, 200, ['Content-Type' => 'image/jpeg']);
```

### Automatic refresh

By default, `url()` will automatically regenerate images older than 30 days. When a stale image is found, a background job is dispatched and the existing URL is returned in the meantime - so users never see a broken image. This follows a stale-while-revalidate approach, whereby the current generated image is served while the new image regenerates.

Configure the threshold in `config/unfurl.php`:

```php
// Refresh images older than 30 days (the default)
'refresh_after_days' => 30,

// Disable automatic refresh
'refresh_after_days' => null,
```

### Variants

Generate images at different dimensions for different platforms:

```php
// config/unfurl.php
'variants' => [
    'twitter' => ['width' => 1200, 'height' => 600],
    'square'  => ['width' => 1200, 'height' => 1200],
],
```

```php
OgImage::for($post)->template('basic', ['title' => $post->title])
    ->variant('twitter')
    ->generate();

$twitterUrl = OgImage::for($post)->url('twitter');
```

### Deleting images

Remove all generated images for a key from storage and the database:

```php
OgImage::for($post)->delete();
```

### Working with models

Any Eloquent model can be passed directly - a deterministic key is generated from the model's morph class and primary key:

```php
OgImage::for($post)->template('dark', ['title' => $post->title])->url();
OgImage::for($user)->screenshot($user->profile_url)->url();
```

## Drivers

- **Cloudflare Browser Rendering** (default) - Uses the Cloudflare Browser Rendering API
- **Browsershot** - Uses [spatie/browsershot](https://github.com/spatie/browsershot) for local rendering

## Configuration

After publishing the config file (`config/unfurl.php`), the following options are available:

| Key | Default | Env Variable | Description |
|-----|---------|--------------|-------------|
| `driver` | `'cloudflare'` | `UNFURL_DRIVER` | Rendering driver: `"cloudflare"` or `"browsershot"` |
| `drivers.cloudflare.account_id` | `null` | `CLOUDFLARE_ACCOUNT_ID` | Cloudflare account ID |
| `drivers.cloudflare.api_token` | `null` | `CLOUDFLARE_BROWSER_RENDERING_TOKEN` | Cloudflare Browser Rendering API token |
| `drivers.browsershot.node_binary` | `null` | `UNFURL_NODE_BINARY` | Path to Node binary (Browsershot), leave blank for default |
| `drivers.browsershot.npm_binary` | `null` | `UNFURL_NPM_BINARY` | Path to npm binary (Browsershot), leave blank for default |
| `drivers.browsershot.chrome_path` | `null` | `UNFURL_CHROME_PATH` | Path to Chrome binary (Browsershot), leave blank for default |
| `storage.disk` | `'public'` | `UNFURL_DISK` | Laravel filesystem disk for storing images |
| `storage.path` | `'og-images'` | `UNFURL_PATH` | Base folder within the disk |
| `width` | `1200` | | Default image width in pixels |
| `height` | `630` | | Default image height in pixels |
| `variants` | `[]` | | Named variants with custom dimensions (e.g. `'twitter' => ['width' => 1200, 'height' => 600]`) |
| `queue.enabled` | `true` | | Dispatch generation jobs via the queue |
| `queue.connection` | `null` | `UNFURL_QUEUE_CONNECTION` | Queue connection name, leave blank for Laravel default |
| `queue.name` | `null` | `UNFURL_QUEUE` | Queue name, leave blank for Laravel default |
| `queue.without_overlapping` | `true` | | Apply `WithoutOverlapping` middleware to prevent concurrent jobs for the same key/variant |
| `queue.rate_limit` | `6` | | Maximum jobs per minute. Set to `null` or `false` to disable rate limiting |
| `generate_on_access` | `true` | | Auto-dispatch generation when `url()` is called with no existing image |
| `refresh_after_days` | `30` | | Regenerate images older than this many days. Set to `null` to disable. Affects `url()` calls.|
| `format` | `'jpeg'` | | Output format: `"jpeg"` or `"png"` |
| `device_scale_factor` | `2` | | Device scale factor for rendering (2 = retina) |
| `template_prefix` | `'unfurl::templates'` | | View namespace prefix for resolving template names |
| `route.enabled` | `false` | | Register the template render route (required for `template()`) |
| `route.prefix` | `'unfurl'` | | URL prefix for the template render route |
| `route.middleware` | `[]` | | Additional middleware for the template render route |

## Development

🧹 Keep a modern codebase with **Pint**:
```bash
composer lint
```

✅ Run refactors using **Rector**:
```bash
composer refactor
```

⚗️ Run static analysis using **PHPStan**:
```bash
composer test:types
```

✅ Run unit tests using **Pest**:
```bash
composer test:unit
```

🚀 Run the entire test suite:
```bash
composer test
```

## License

Unfurl for Laravel is open-sourced software licensed under the **[MIT license](LICENSE.md)**.
