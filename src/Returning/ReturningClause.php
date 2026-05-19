<?php

declare(strict_types=1);

/**
 * Copyright (c) 2025-2026 Maksim Tyugaev
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/tugmaks/doctrine-walkers
 */

namespace Tugmaks\DoctrineWalkers\Returning;

final readonly class ReturningClause
{
    /**
     * @param list<string>|string $columns
     */
    public function __construct(private array|string $columns = '*')
    {
    }

    public function toSQL(): string
    {
        if (\is_array($this->columns)) {
            return 'RETURNING ' . \implode(', ', $this->columns);
        }

        return 'RETURNING ' . $this->columns;
    }
}
