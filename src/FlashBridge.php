<?php
declare(strict_types=1);

namespace MonkeysLegion\Inertia;

use MonkeysLegion\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Bridges session flash data into Inertia shared props.
 *
 * Reads 'success', 'error', 'warning', 'info' flash keys from the session
 * and shares them as the 'flash' prop on every Inertia page.
 */
final class FlashBridge implements MiddlewareInterface
{
    private const array FLASH_KEYS = ['success', 'error', 'warning', 'info', 'message'];

    public function __construct(
        private readonly Inertia $inertia,
        private readonly ?SessionInterface $session = null,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Only bridge flash data for Inertia requests
        if ($request->hasHeader('X-Inertia') || !$this->isJsonRequest($request)) {
            $this->bridgeFlashData();
        } else {
            // Also bridge for regular requests (flash works for non-Inertia too)
            $this->bridgeFlashData();
        }

        return $handler->handle($request);
    }

    /**
     * Read flash data from session and share with Inertia.
     */
    private function bridgeFlashData(): void
    {
        if ($this->session === null) {
            return;
        }

        $flash = [];

        foreach (self::FLASH_KEYS as $key) {
            $value = $this->session->getFlash($key);
            if ($value !== null) {
                $flash[$key] = $value;
            }
        }

        // Also check for validation errors
        $errors = $this->session->getFlash('errors');
        if ($errors !== null) {
            $flash['errors'] = $errors;
        }

        if ($flash !== []) {
            $this->inertia->share(['flash' => $flash]);
        }
    }

    /**
     * Check if the request expects JSON.
     */
    private function isJsonRequest(ServerRequestInterface $request): bool
    {
        $accept = $request->getHeaderLine('Accept');
        return str_contains($accept, 'application/json');
    }
}
