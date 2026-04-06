<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

beforeEach(function (): void {
    View::addLocation(__DIR__.'/../fixtures/views');
});

test('renders template with decoded params via signed url', function (): void {
    $url = URL::signedRoute('og-image.render', [
        'template' => 'og-test',
        'params' => base64_encode(json_encode(['title' => 'Hello World'])),
    ]);

    $response = $this->get($url);

    $response->assertOk();
    $response->assertSee('Hello World');
});

test('rejects unsigned requests', function (): void {
    $url = URL::route('og-image.render', ['template' => 'og-test']);

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('returns 404 for non-existent template', function (): void {
    $url = URL::signedRoute('og-image.render', [
        'template' => 'non-existent-view',
    ]);

    $response = $this->get($url);

    $response->assertNotFound();
});

test('renders template without params', function (): void {
    $url = URL::signedRoute('og-image.render', [
        'template' => 'og-test',
        'params' => base64_encode(json_encode(['title' => 'No Params'])),
    ]);

    $response = $this->get($url);

    $response->assertOk();
    $response->assertSee('No Params');
});
