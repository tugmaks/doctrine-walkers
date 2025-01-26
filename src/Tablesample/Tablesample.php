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

namespace Tugmaks\DoctrineWalkers\Tablesample;

final class Tablesample
{
    public function __construct(
        private readonly TablesampleMethod $tablesampleMethod,
        private readonly float $percentage,
    ) {
        if (0.00 > $percentage || 100.00 < $percentage) {
            throw new TablesampleWalkerException('A percentage must be between 0 and 100');
        }
    }

    public function toSQL(): string
    {
        return 'TABLESAMPLE ' .
            $this->tablesampleMethod->name .
            '(' .
            $this->percentage .
            ')';
    }
}
