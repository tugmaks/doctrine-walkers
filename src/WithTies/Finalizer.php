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

namespace Tugmaks\DoctrineWalkers\WithTies;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Exec\AbstractSqlExecutor;
use Doctrine\ORM\Query\Exec\FinalizedSelectExecutor;
use Doctrine\ORM\Query\Exec\SqlFinalizer;

final readonly class Finalizer implements SqlFinalizer
{
    public function __construct(private string $sql)
    {
    }

    public function createExecutor(Query $query): AbstractSqlExecutor
    {
        return new FinalizedSelectExecutor($this->finalize($query));
    }

    private function finalize(Query $query): string
    {
        $finalizedSql = (new Query\Exec\SingleSelectSqlFinalizer($this->sql))
            ->createExecutor($query)
            ->getSqlStatements();

        $limit = $query->getMaxResults();
        $offset = $query->getFirstResult();

        if (null !== $limit) {
            $finalizedSql = 0 === $offset
                ? \preg_replace(
                    '/LIMIT (\d+)/',
                    'FETCH NEXT $1 ROWS WITH TIES',
                    $finalizedSql,
                )
                : \preg_replace(
                    '/LIMIT (\d+) OFFSET (\d+)/',
                    'OFFSET $2 FETCH NEXT $1 ROWS WITH TIES',
                    $finalizedSql,
                );
        }

        \assert(\is_string($finalizedSql));

        return $finalizedSql;
    }
}
