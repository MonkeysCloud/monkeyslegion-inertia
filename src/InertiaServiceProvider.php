<?php
declare(strict_types=1);

namespace MonkeysLegion\Inertia;

use MonkeysLegion\DI\Container;
use MonkeysLegion\DI\ServiceProviderInterface;

/**
 * Registers Inertia services in the DI container.
 */
final class InertiaServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        // Singleton Inertia service
        $container->singleton(Inertia::class, function (Container $c): Inertia {
            $inertia = new Inertia();

            // Set version from Vite manifest if available
            $manifestPath = defined('ML_BASE_PATH')
                ? ML_BASE_PATH . '/public/build/manifest.json'
                : 'public/build/manifest.json';

            if (is_file($manifestPath)) {
                $inertia->version(md5_file($manifestPath) ?: '');
            }

            // Set default root view
            $inertia->rootView('layouts.inertia-app');

            return $inertia;
        });

        // Middleware (transient)
        $container->set(InertiaMiddleware::class, function (Container $c): InertiaMiddleware {
            return new InertiaMiddleware($c->get(Inertia::class));
        });
    }
}
