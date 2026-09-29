<?php
declare(strict_types=1);

namespace MonkeysLegion\Inertia;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware implementing the Inertia.js protocol.
 *
 * - Detects X-Inertia requests and sets appropriate headers
 * - Checks X-Inertia-Version for asset version mismatch (409 response)
 * - Forces 303 See Other for POST/PUT/DELETE/PATCH redirects
 * - Sets Vary: X-Inertia on all responses
 */
final class InertiaMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Inertia $inertia,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Only process for requests that have the X-Inertia header
        if (!$request->hasHeader('X-Inertia')) {
            return $handler->handle($request);
        }

        // Check for version mismatch
        $requestVersion = $request->getHeaderLine('X-Inertia-Version');
        $currentVersion = $this->inertia->getVersion();

        if ($requestVersion !== '' && $requestVersion !== $currentVersion) {
            return $this->versionMismatchResponse($currentVersion);
        }

        $response = $handler->handle($request);

        // Force 303 redirect for POST/PUT/DELETE/PATCH
        $method = strtoupper($request->getMethod());
        $status = $response->getStatusCode();

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && $status >= 300 && $status < 400
        ) {
            $response = $response->withStatus(303);
        }

        // Set Vary header for caching
        $response = $response->withHeader('Vary', 'X-Inertia');

        return $response;
    }

    /**
     * Return a 409 Conflict response with the new version.
     */
    private function versionMismatchResponse(string $currentVersion): ResponseInterface
    {
        return new \MonkeysLegion\Http\Message\Response(
            \MonkeysLegion\Http\Message\Stream::createFromString('Version mismatch'),
            409,
            [
                'X-Inertia-Version' => $currentVersion,
                'Content-Type' => 'text/plain',
            ],
        );
    }
}
