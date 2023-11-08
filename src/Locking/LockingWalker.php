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

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\AST;
use Doctrine\ORM\Query\SqlWalker;

final class LockingWalker extends SqlWalker
{
    public const LOCKING_CLAUSE = 'LockingWalker.LockingClause';

    public function walkSelectStatement(AST\SelectStatement $AST): string
    {
        $query = $this->getQuery();
        $lockMode = $query->getHint(Query::HINT_LOCK_MODE) ?: LockMode::NONE;

        if (LockMode::NONE !== $lockMode) {
            throw new LockingWalkerException('Query is already marked for locking...');
        }

        $lockClause = $query->getHint(self::LOCKING_CLAUSE);

        if (!$lockClause instanceof LockingClause) {
            throw new LockingWalkerException('Locking clause not provided');
        }

        return \sprintf('%s %s', parent::walkSelectStatement($AST), $lockClause->toSQL());
    }
}
