<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Maksim Tugaev
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/tugmaks/doctrine-walkers
 */

namespace Tugmaks\DoctrineWalkers\Locking;

enum Option: string
{
    case NOWAIT = 'NOWAIT';
    case SKIP_LOCKED = 'SKIP LOCKED';
}
