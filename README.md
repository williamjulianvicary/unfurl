# Ogify for Laravel

[![Tests](https://github.com/williamjulianvicary/ogify/actions/workflows/tests.yml/badge.svg)](https://github.com/williamjulianvicary/ogify/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/williamjulianvicary/ogify)](https://packagist.org/packages/williamjulianvicary/ogify)
[![License](https://img.shields.io/packagist/l/williamjulianvicary/ogify)](https://packagist.org/packages/williamjulianvicary/ogify)

Most OG image packages for Laravel assume you have Browsershot (and therefore a local Node/Puppeteer install) available — that's a non-starter on managed platforms like Laravel Cloud. They also tend to serve images through PHP on every request and offer limited templating.

Ogify takes a different approach:

- **Driver-based rendering** — ship with Cloudflare Browser Rendering (no server-side browser needed) or fall back to Browsershot when you can.
- **Static file serving** — generated images are stored on any Laravel filesystem disk (public by default) and served directly by your web server or CDN, not through PHP.
- **Queue-first generation** — images are rendered in the background via Laravel's queue so page loads are never blocked.
- **Built-in templates** — includes ready-to-use Blade templates (`basic`, `dark`, `minimal`) with automatic text fitting - customise the templates or bring your own.

> **Requires [PHP 8.2+](https://php.net/releases/)** and **[Laravel 11+](https://laravel.com)**

## Installation

```bash
composer require williamjulianvicary/ogify
```

Publish the config, migrations and blade og image templates:

```bash
php artisan vendor:publish --provider="WilliamJulianVicary\Ogify\OgImageServiceProvider"
php artisan migrate
```

## How it works

1. You define a **source** — either a URL to screenshot or a Blade template to render (templates are included)
    1. For URL based rendering, the URL is loaded by the service at a relevant viewport.
    2. For template based rendering, a URL is passed to the driver (default: `/og-image/render/{template}`) to render the template.
2. Ogify dispatches a queued job that uses a **driver** (Cloudflare Browser Rendering, the default or Browsershot) to take a screenshot of that source URL - for templates this is via a Signed URL for security.
    1. The queued jobs implements `ShouldBeUnique` to block excessive requests.
3. The image is stored on the Laravel filesystem disk (public by default, configurable in the config) and tracked in the database with a deterministic key.
4. On subsequent requests, `url()` returns the stored image URL instantly — no re-rendering.

When `generate_on_access` is enabled (the default), the first call to `url()` will automatically dispatch generation in the background and return the expected URL, so images are created lazily without blocking your response.

## Usage

Every operation starts with `OgImage::for()`, which accepts a string key or an Eloquent model (a deterministic key is derived automatically from the model).

```php
use WilliamJulianVicary\Ogify\Facades\OgImage;
```

### Setting the source

You can set the source as a URL to screenshot or a Blade template to render.

**Screenshot a URL:**

```php
OgImage::for('my-page')->screenshot('https://example.com')->url();
```

**Render from a Blade template:**

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

To use templates, enable the render route in `config/og-image.php`:

```php
'route' => [
    'enabled' => true,
],
```

### `url()`, `generate()`, and `render()`

The builder provides three ways to produce an image:

| Method       | Dispatches job | Returns                  | Use case                                                  |
|--------------|----------------|--------------------------|-----------------------------------------------------------|
| `url()`      | Lazily         | `?string` — the image URL | **Most common.** Returns the stored URL if the image exists. When `generate_on_access` is enabled (the default), dispatches a background job on first access and returns the expected URL. |
| `generate()` | Always         | `string` — the expected URL | Forces a (re)generation job to be dispatched and returns the expected URL. Useful for seeding or regenerating images. |
| `render()`   | No             | `string` — raw image bytes | Renders the screenshot synchronously in-process. No job, no storage. Useful for streaming responses or custom storage logic. |

**Typical usage — `url()` in a Blade view:**

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

By default, `url()` will automatically regenerate images older than 30 days. When a stale image is found, a background job is dispatched and the existing URL is returned in the meantime — so users never see a broken image.

Configure the threshold in `config/og-image.php`:

```php
// Refresh images older than 30 days (the default)
'refresh_after_days' => 30,

// Disable automatic refresh
'refresh_after_days' => null,
```

### Variants

Generate images at different dimensions for different platforms:

```php
// config/og-image.php
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

Any Eloquent model can be passed directly — a deterministic key is generated from the model's morph class and primary key:

```php
OgImage::for($post)->template('dark', ['title' => $post->title])->url();
OgImage::for($user)->screenshot($user->profile_url)->url();
```

## Drivers

- **Cloudflare Browser Rendering** (default) — Uses the Cloudflare Browser Rendering API
- **Browsershot** — Uses [spatie/browsershot](https://github.com/spatie/browsershot) for local rendering

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

Ogify for Laravel is open-sourced software licensed under the **[MIT license](LICENSE.md)**.
