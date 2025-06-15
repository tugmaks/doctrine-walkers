<?php

declare(strict_types=1);

/**
 * Copyright (c) 2025 Maksim Tugaev
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/tugmaks/doctrine-walkers
 */

namespace Tugmaks\DoctrineWalkers\Locking;

final readonly class LockingClause
{
    public function __construct(
        private LockStrength $lockStrength,
        private ?Option $option = null,
    ) {
    }

    public function toSQL(): string
    {
        $sql = 'FOR ' . $this->lockStrength->value;

        if (null !== $this->option) {
            $sql .= ' ' . $this->option->value;
        }

        return $sql;
    }
}
