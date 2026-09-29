<?php
declare(strict_types=1);

namespace MonkeysLegion\Inertia\Ssr;

/**
 * MonKeysLegion Framework — Inertia Package
 *
 * Represents a server-side rendered Inertia response.
 *
 * When SSR is enabled, the Node.js SSR server returns the pre-rendered
 * HTML for the page component. This class wraps that response and
 * merges it with the Inertia page payload.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final readonly class SsrResponse
{
    public function __construct(
        public string $head,
        public string $body,
    ) {}

    /**
     * Render the full HTML document.
     *
     * @param string $pagePayload The JSON-encoded Inertia page object.
     * @param string $rootElement The root div ID (default: 'app').
     */
    public function render(string $pagePayload, string $rootElement = 'app'): string
    {
        return $this->head
            . '<div id="' . htmlspecialchars($rootElement) . '" data-page="'
            . htmlspecialchars($pagePayload, ENT_QUOTES, 'UTF-8')
            . '">' . $this->body . '</div>';
    }

    /**
     * Create from a raw SSR server response (JSON).
     *
     * Expected format: {"head": [...], "body": "..."}
     *
     * @param string $json
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true) ?? [];

        $head = '';
        if (isset($data['head']) && is_array($data['head'])) {
            $head = implode("\n", $data['head']);
        } elseif (isset($data['head']) && is_string($data['head'])) {
            $head = $data['head'];
        }

        $body = $data['body'] ?? '';

        return new self(head: $head, body: $body);
    }
}
