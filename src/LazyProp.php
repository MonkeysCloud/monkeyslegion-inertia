<?php
declare(strict_types=1);

namespace MonkeysLegion\Inertia;

/**
 * Wraps a callable that is only evaluated during partial reloads.
 */
final class LazyProp
{
    public function __construct(
        private readonly \Closure $callback,
    ) {}

    /**
     * Evaluate the lazy prop.
     *
     * @return mixed
     */
    public function __invoke(): mixed
    {
        return ($this->callback)();
    }

    /**
     * Evaluate the lazy prop (explicit method).
     *
     * @return mixed
     */
    public function resolve(): mixed
    {
        return ($this->callback)();
    }
}
