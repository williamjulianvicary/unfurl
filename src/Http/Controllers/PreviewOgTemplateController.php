<?php

declare(strict_types=1);

namespace WilliamJulianVicary\Ogify\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

final readonly class PreviewOgTemplateController
{
    public function __invoke(Request $request, string $template): Response
    {
        if (! app()->hasDebugModeEnabled()) {
            abort(403, 'OG image preview is only available in debug mode.');
        }

        $resolvedTemplate = $this->resolveTemplateName($template);

        if (! View::exists($resolvedTemplate)) {
            abort(404, sprintf('OG image template [%s] not found.', $resolvedTemplate));
        }

        $params = $request->except(['_', 'template']);

        return new Response(
            view($resolvedTemplate, $params)->render(),
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
