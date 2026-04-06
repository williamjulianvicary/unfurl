<?php

declare(strict_types=1);

namespace WilliamJulianVicary\Ogify\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

final readonly class RenderOgTemplateController
{
    public function __invoke(Request $request, string $template): Response
    {
        $resolved = $this->resolveTemplateName($template);

        if (View::exists($resolved)) {
            $template = $resolved;
        } elseif (! View::exists($template)) {
            abort(404, sprintf('OG image template [%s] not found.', $template));
        }

        $params = [];

        if ($request->has('params')) {
            $raw = $request->query('params', '');
            $decoded = base64_decode(is_string($raw) ? $raw : '', true);

            if ($decoded !== false) {
                $parsed = json_decode($decoded, true);
                $params = is_array($parsed) ? $parsed : [];
            }
        }

        return new Response(
            view($template, $params)->render(),
            200,
            ['Content-Type' => 'text/html'],
        );
    }

    private function resolveTemplateName(string $name): string
    {
        if (str_contains($name, '::') || str_contains($name, '.')) {
            return $name;
        }

        $prefix = config()->string('og-image.template_prefix', 'og-image::templates');

        return sprintf('%s.%s', $prefix, $name);
    }
}
