<?php

declare(strict_types=1);

namespace Itrblueboost\Entity\Traits;

/**
 * Shared status helper methods for entities with pending/accepted/rejected workflow.
 *
 * Requires the using class to define STATUS_PENDING and STATUS_ACCEPTED constants.
 */
trait FaqStatusTrait
{
    /**
     * Check if this entity is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if this entity is accepted.
     *
     * @return bool
     */
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }
}
