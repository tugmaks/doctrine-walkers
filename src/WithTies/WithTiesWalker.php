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

namespace Tugmaks\DoctrineWalkers\WithTies;

use Doctrine\ORM\Query\AST;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\Exec\SingleSelectSqlFinalizer;
use Doctrine\ORM\Query\Exec\SqlFinalizer;
use Doctrine\ORM\Query\OutputWalker;
use Doctrine\ORM\Query\SqlWalker;

final class WithTiesWalker extends SqlWalker implements OutputWalker
{
    public function getFinalizer(AST\DeleteStatement|AST\SelectStatement|AST\UpdateStatement $AST): SqlFinalizer
    {
        \assert($AST instanceof SelectStatement);

        return new SingleSelectSqlFinalizer($this->createWithTiesSql($AST));
    }

    private function createWithTiesSql(AST\SelectStatement $AST): string
    {
        $limit = $this->getQuery()->getMaxResults();
        $offset = $this->getQuery()->getFirstResult();
        $sql = parent::walkSelectStatement($AST);

        if (null !== $limit || 0 !== $offset) {
            $this->getQuery()->setMaxResults(null)->setFirstResult(0);
            $sql = \preg_replace(
                '/LIMIT (\d+) OFFSET (\d+)/',
                'OFFSET $2 FETCH NEXT $1 ROWS WITH TIES',
                $sql,
            );

            $sql = \preg_replace(
                '/LIMIT (\d+)/',
                'FETCH NEXT $1 ROWS WITH TIES',
                (string) $sql,
            );
        }

        \assert(\is_string($sql));

        return $sql;
    }
}
