# MonKeysLegion Inertia.js

Inertia.js server-side adapter for the MonKeysLegion framework — bridges PHP controllers with React/Vue single-page applications.

## Features

- **Full Inertia.js protocol** — X-Inertia headers, version mismatch (409), 303 redirects
- **Server-side rendering (SSR)** — optional Node.js SSR with graceful CSR fallback
- **Shared props** — global props available on every page
- **Lazy props** — deferred evaluation for performance
- **Flash message bridge** — session flash data to Inertia props
- **PSR-15 middleware** — drop-in middleware pipeline integration
- **SSR manifest reader** — Vite SSR manifest for CSS/JS chunk resolution

## Installation

```bash
composer require monkeyscloud/monkeyslegion-inertia
```

## Basic Usage

```php
use MonkeysLegion\Inertia\Inertia;

// In a controller
return Inertia::render('Dashboard', [
    'user' => $user,
    'stats' => $stats,
]);
```

## Server-Side Rendering

```php
$inertia = $container->get(Inertia::class);
$inertia->enableSsr('http://localhost:13714');

// Now all renders go through the Node.js SSR server
// Falls back to client-side rendering if SSR is unavailable
```

## Middleware

Register `InertiaMiddleware` in your middleware pipeline:

```hocon
# config/middleware.mlc
middleware {
    global = ["inertia"]
}
```

## License

MIT © MonKeysCloud
