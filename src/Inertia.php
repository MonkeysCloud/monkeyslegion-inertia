<?php
declare(strict_types=1);

namespace MonkeysLegion\Inertia;

use MonkeysLegion\Inertia\Ssr\SsrResponse;

/**
 * Holds shared props that are available on every Inertia page.
 *
 * Supports optional server-side rendering (SSR) via a Node.js
 * SSR server. When enabled, page components are pre-rendered on
 * the server for faster first paint and SEO.
 */
final class Inertia
{
    /** @var array<string, mixed> */
    private array $sharedProps = [];

    private string $version = '';
    private ?string $rootView = null;

    private bool $ssrEnabled = false;
    private ?string $ssrUrl = null;

    /**
     * Render an Inertia page.
     *
     * @param string $component The frontend component name (e.g., 'Dashboard')
     * @param array<string, mixed> $props Props passed to the component
     * @return InertiaResponse
     */
    public function render(string $component, array $props = []): InertiaResponse
    {
        return new InertiaResponse(
            component: $component,
            props: array_merge($this->sharedProps, $props),
            version: $this->version,
            rootView: $this->rootView,
            inertia: $this,
        );
    }

    /**
     * Share data with all Inertia pages.
     *
     * @param array<string, mixed> $props
     */
    public function share(array $props): self
    {
        $this->sharedProps = array_merge($this->sharedProps, $props);
        return $this;
    }

    /**
     * Mark a prop as lazy — only evaluated when requested via partial reload.
     *
     * @param callable $callback
     * @return LazyProp
     */
    public function lazy(callable $callback): LazyProp
    {
        return new LazyProp($callback);
    }

    /**
     * Set the asset version (for cache invalidation).
     */
    public function version(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    /**
     * Get the current version.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Set the root HTML view template name (dot notation).
     */
    public function rootView(?string $view): self
    {
        $this->rootView = $view;
        return $this;
    }

    /**
     * Get shared props.
     *
     * @return array<string, mixed>
     */
    public function getSharedProps(): array
    {
        return $this->sharedProps;
    }

    /**
     * Enable server-side rendering.
     *
     * @param string $ssrUrl The Node.js SSR server URL (e.g., http://localhost:13714).
     */
    public function enableSsr(string $ssrUrl = 'http://localhost:13714'): self
    {
        $this->ssrEnabled = true;
        $this->ssrUrl = $ssrUrl;
        return $this;
    }

    /**
     * Disable server-side rendering.
     */
    public function disableSsr(): self
    {
        $this->ssrEnabled = false;
        $this->ssrUrl = null;
        return $this;
    }

    /**
     * Check if SSR is enabled.
     */
    public function isSsrEnabled(): bool
    {
        return $this->ssrEnabled;
    }

    /**
     * Render a page via the SSR server.
     *
     * Returns null if SSR is disabled or the SSR server is unreachable.
     *
     * @param string $component The frontend component name.
     * @param array<string, mixed> $props Props passed to the component.
     * @param string $url The current request URL.
     */
    public function renderSsr(string $component, array $props, string $url): ?SsrResponse
    {
        if (!$this->ssrEnabled || $this->ssrUrl === null) {
            return null;
        }

        $page = [
            'component' => $component,
            'props'     => array_merge($this->sharedProps, $props),
            'url'       => $url,
            'version'   => $this->version,
        ];

        $payload = json_encode($page, JSON_THROW_ON_ERROR);

        $ch = curl_init($this->ssrUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null; // Graceful fallback to CSR
        }

        return SsrResponse::fromJson((string) $response);
    }

    /**
     * Clear shared props (for testing).
     */
    public function flush(): void
    {
        $this->sharedProps = [];
        $this->ssrEnabled = false;
        $this->ssrUrl = null;
    }
}
