<?php
declare(strict_types=1);

namespace MonkeysLegion\Inertia\Tests\Unit;

use MonkeysLegion\Inertia\Inertia;
use MonkeysLegion\Inertia\InertiaResponse;
use MonkeysLegion\Inertia\LazyProp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Tests for the Inertia service and response generation.
 */
final class InertiaTest extends TestCase
{
    private function makeRequest(bool $hasInertia = false, string $url = '/dashboard'): ServerRequestInterface
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn($url);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('hasHeader')->willReturnCallback(fn($name) => $name === 'X-Inertia' && $hasInertia);
        $request->method('getHeaderLine')->willReturn('');

        return $request;
    }

    #[Test]
    public function render_returns_inertia_response(): void
    {
        $inertia = new Inertia();
        $response = $inertia->render('Dashboard', ['stats' => ['users' => 10]]);

        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    #[Test]
    public function xhr_request_returns_json(): void
    {
        $inertia = new Inertia();
        $inertia->version('abc123');

        $inertiaResponse = $inertia->render('Dashboard', ['stats' => ['users' => 10]]);
        $response = $inertiaResponse->toResponse($this->makeRequest(true));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        self::assertSame('Dashboard', $data['component']);
        self::assertSame(['users' => 10], $data['props']['stats']);
        self::assertSame('/dashboard', $data['url']);
        self::assertSame('abc123', $data['version']);
    }

    #[Test]
    public function initial_load_returns_html(): void
    {
        $inertia = new Inertia();

        $inertiaResponse = $inertia->render('Dashboard', ['stats' => ['users' => 10]]);
        $response = $inertiaResponse->toResponse($this->makeRequest(false));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        self::assertStringContainsString('<div id="app"', $body);
        self::assertStringContainsString('data-page=', $body);
        self::assertStringContainsString('Dashboard', $body);
    }

    #[Test]
    public function shared_props_are_merged(): void
    {
        $inertia = new Inertia();
        $inertia->share(['auth' => ['user' => ['name' => 'John']]]);

        $inertiaResponse = $inertia->render('Dashboard', ['stats' => ['users' => 10]]);
        $response = $inertiaResponse->toResponse($this->makeRequest(true));

        $data = json_decode((string) $response->getBody(), true);
        self::assertArrayHasKey('auth', $data['props']);
        self::assertSame('John', $data['props']['auth']['user']['name']);
        self::assertArrayHasKey('stats', $data['props']);
    }

    #[Test]
    public function lazy_props_excluded_from_full_load(): void
    {
        $inertia = new Inertia();
        $inertia->share([
            'heavy' => $inertia->lazy(fn() => ['big' => 'data']),
            'normal' => 'value',
        ]);

        $inertiaResponse = $inertia->render('Dashboard');
        $response = $inertiaResponse->toResponse($this->makeRequest(true));

        $data = json_decode((string) $response->getBody(), true);
        // Lazy prop should NOT be included in a full load
        self::assertArrayNotHasKey('heavy', $data['props']);
        self::assertArrayHasKey('normal', $data['props']);
    }

    #[Test]
    public function lazy_props_included_in_partial_reload(): void
    {
        $inertia = new Inertia();
        $inertia->share([
            'heavy' => $inertia->lazy(fn() => ['big' => 'data']),
            'normal' => 'value',
        ]);

        $request = $this->createPartialRequest('heavy', 'Dashboard');

        $inertiaResponse = $inertia->render('Dashboard');
        $response = $inertiaResponse->toResponse($request);

        $data = json_decode((string) $response->getBody(), true);
        // Lazy prop SHOULD be included in a partial reload
        self::assertArrayHasKey('heavy', $data['props']);
        self::assertSame(['big' => 'data'], $data['props']['heavy']);
        // Normal prop should NOT be included (only requested props)
        self::assertArrayNotHasKey('normal', $data['props']);
    }

    #[Test]
    public function version_is_set_and_retrieved(): void
    {
        $inertia = new Inertia();
        $inertia->version('test-version-hash');

        self::assertSame('test-version-hash', $inertia->getVersion());
    }

    #[Test]
    public function flush_clears_shared_props(): void
    {
        $inertia = new Inertia();
        $inertia->share(['key' => 'value']);
        $inertia->flush();

        self::assertSame([], $inertia->getSharedProps());
    }

    /**
     * Create a request with partial reload headers.
     */
    private function createPartialRequest(string $partialData, string $partialComponent): ServerRequestInterface
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('/dashboard');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('hasHeader')->willReturnCallback(fn($name) => $name === 'X-Inertia');
        $request->method('getHeaderLine')->willReturnCallback(fn($name) => match ($name) {
            'X-Inertia-Partial-Data' => $partialData,
            'X-Inertia-Partial-Component' => $partialComponent,
            default => '',
        });

        return $request;
    }
}
