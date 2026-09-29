<?php
declare(strict_types=1);

namespace MonkeysLegion\Inertia;

use MonkeysLegion\Http\Message\Response;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Static factory for creating Inertia responses.
 *
 * Usage in controllers:
 *   return InertiaResponseFactory::render('Dashboard', ['stats' => $stats]);
 */
final class ResponseFactory
{
    private static ?Inertia $instance = null;

    /**
     * Set the Inertia instance (called by the service provider).
     */
    public static function setInstance(Inertia $inertia): void
    {
        self::$instance = $inertia;
    }

    /**
     * Render an Inertia page.
     *
     * @param string $component Frontend component name
     * @param array<string, mixed> $props Props for the component
     * @return \MonkeysLegion\Http\Message\Response
     */
    public static function render(string $component, array $props = []): Response
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Inertia not initialized. Ensure InertiaServiceProvider is registered.');
        }

        // We need a request to build the response — use globals as fallback.
        // In a real controller, the request is injected; this factory is a convenience.
        $response = self::$instance->render($component, $props);

        // Create a minimal request from globals
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $request = (new \Laminas\Diactoros\ServerRequestFactory())
            ->createServerRequest($method, $uri);

        // Copy X-Inertia headers from globals
        if (isset($_SERVER['HTTP_X_INERTIA'])) {
            $request = $request->withHeader('X-Inertia', 'true');
        }
        if (isset($_SERVER['HTTP_X_INERTIA_VERSION'])) {
            $request = $request->withHeader('X-Inertia-Version', $_SERVER['HTTP_X_INERTIA_VERSION']);
        }
        if (isset($_SERVER['HTTP_X_INERTIA_PARTIAL_DATA'])) {
            $request = $request->withHeader('X-Inertia-Partial-Data', $_SERVER['HTTP_X_INERTIA_PARTIAL_DATA']);
        }
        if (isset($_SERVER['HTTP_X_INERTIA_PARTIAL_COMPONENT'])) {
            $request = $request->withHeader('X-Inertia-Partial-Component', $_SERVER['HTTP_X_INERTIA_PARTIAL_COMPONENT']);
        }

        return $response->toResponse($request);
    }
}
