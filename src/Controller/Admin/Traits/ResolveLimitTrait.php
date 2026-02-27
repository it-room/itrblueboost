<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin\Traits;

/**
 * Trait for resolving and validating per-page limits.
 */
trait ResolveLimitTrait
{
    /**
     * Resolve and validate the per-page limit.
     *
     * @param int $requested Requested limit
     *
     * @return int
     */
    private function resolveLimit(int $requested): int
    {
        $allowed = [10, 20, 50, 100];

        return in_array($requested, $allowed, true) ? $requested : 20;
    }
}
