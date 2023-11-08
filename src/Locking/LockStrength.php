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

enum LockStrength: string
{
    case UPDATE = 'UPDATE';
    case NO_KEY_UPDATE = 'NO KEY UPDATE';
    case SHARE = 'SHARE';
    case KEY_SHARE = 'KEY SHARE';
}
