<?php
declare(strict_types=1);

namespace MonkeysLegion\Inertia;

use MonkeysLegion\Http\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Inertia page response — returns JSON for XHR requests, HTML for initial loads.
 *
 * Implements the official Inertia.js protocol:
 * - X-Inertia header → JSON {component, props, url, version}
 * - No X-Inertia header → HTML with <div id="app" data-page="{...}">
 * - X-Inertia-Partial-Data → only include requested props
 * - Version mismatch → handled by middleware (409)
 */
final class InertiaResponse
{
    /**
     * @param string $component Frontend component name
     * @param array<string, mixed> $props Props for the component
     * @param string $version Asset version hash
     * @param string|null $rootView Root HTML template name (dot notation)
     * @param Inertia $inertia The Inertia service (for resolving lazy props)
     */
    public function __construct(
        private readonly string $component,
        private readonly array $props,
        private readonly string $version,
        private readonly ?string $rootView,
        private readonly Inertia $inertia,
    ) {}

    /**
     * Convert to a PSR-7 Response based on the request type.
     */
    public function toResponse(ServerRequestInterface $request): ResponseInterface
    {
        $page = $this->buildPageData($request);

        // XHR Inertia request → JSON response
        if ($request->hasHeader('X-Inertia')) {
            return Response::json($page, 200)
                ->withHeader('X-Inertia', 'true')
                ->withHeader('Vary', 'X-Inertia');
        }

        // Initial load → HTML response with embedded page data
        $encoded = htmlspecialchars(json_encode($page, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES);

        $html = $this->renderRootView($encoded);

        return Response::html($html, 200)
            ->withHeader('Vary', 'X-Inertia');
    }

    /**
     * Build the Inertia page data object.
     *
     * @return array{component: string, props: array<string, mixed>, url: string, version: string}
     */
    private function buildPageData(ServerRequestInterface $request): array
    {
        $props = $this->resolveProps($request);

        return [
            'component' => $this->component,
            'props'     => $props,
            'url'       => (string) $request->getUri(),
            'version'   => $this->version,
        ];
    }

    /**
     * Resolve props: evaluate lazy props, filter partial reloads.
     *
     * @return array<string, mixed>
     */
    private function resolveProps(ServerRequestInterface $request): array
    {
        $partialData = $request->getHeaderLine('X-Inertia-Partial-Data');
        $partialComponent = $request->getHeaderLine('X-Inertia-Partial-Component');

        // Partial reload: only include requested props, and only for matching component
        if ($partialData !== '' && $partialComponent === $this->component) {
            $requested = explode(',', $partialData);
            $resolved = [];

            foreach ($this->props as $key => $value) {
                if (in_array($key, $requested, true)) {
                    $resolved[$key] = $this->resolveValue($value);
                }
            }

            return $resolved;
        }

        // Full reload: resolve all non-lazy props, resolve lazy only if explicitly requested
        $resolved = [];
        foreach ($this->props as $key => $value) {
            if ($value instanceof LazyProp) {
                // Lazy props are only included in partial reloads that explicitly request them
                continue;
            }
            $resolved[$key] = $this->resolveValue($value);
        }

        return $resolved;
    }

    /**
     * Resolve a single prop value (evaluate closures/lazy props).
     *
     * @param mixed $value
     * @return mixed
     */
    private function resolveValue(mixed $value): mixed
    {
        if ($value instanceof LazyProp) {
            return $value->resolve();
        }

        if ($value instanceof \Closure) {
            return $value();
        }

        if (is_array($value)) {
            return array_map(fn($v) => $this->resolveValue($v), $value);
        }

        return $value;
    }

    /**
     * Render the root HTML view with the embedded page data.
     *
     * If a root view template name is set, it should be rendered by the template engine.
     * For simplicity, we generate a minimal HTML shell that the frontend mounts on.
     */
    private function renderRootView(string $encodedPageData): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MonKeysLegion</title>
@vite(['resources/js/app.tsx'])
</head>
<body>
<div id="app" data-page="{$encodedPageData}"></div>
</body>
</html>
HTML;
    }
}
