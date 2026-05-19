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

use Doctrine\ORM\Query\AST;
use Doctrine\ORM\Query\AST\DeleteStatement;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\AST\UpdateStatement;
use Doctrine\ORM\Query\Exec\PreparedExecutorFinalizer;
use Doctrine\ORM\Query\Exec\SingleSelectSqlFinalizer;
use Doctrine\ORM\Query\Exec\SqlFinalizer;
use Doctrine\ORM\Query\OutputWalker;
use Doctrine\ORM\Query\SqlWalker;

final class ReturningWalker extends SqlWalker implements OutputWalker
{
    public const RETURNING_CLAUSE = 'ReturningWalker.ReturningClause';

    public function walkSelectStatement(AST\SelectStatement $AST): string
    {
        return \sprintf('%s %s', $this->createSqlForFinalizer($AST), $this->getReturningSql());
    }

    public function walkUpdateStatement(AST\UpdateStatement $updateStatement): string
    {
        return \sprintf(
            '%s %s',
            parent::walkUpdateStatement($updateStatement),
            $this->getReturningSql(),
        );
    }

    public function walkDeleteStatement(AST\DeleteStatement $deleteStatement): string
    {
        return \sprintf(
            '%s %s',
            parent::walkDeleteStatement($deleteStatement),
            $this->getReturningSql(),
        );
    }

    public function getFinalizer(DeleteStatement|SelectStatement|UpdateStatement $AST): SqlFinalizer
    {
        return match (true) {
            $AST instanceof SelectStatement => new SingleSelectSqlFinalizer($this->walkSelectStatement($AST)),
            $AST instanceof UpdateStatement => new PreparedExecutorFinalizer($this->createUpdateStatementExecutor($AST)),
            $AST instanceof DeleteStatement => new PreparedExecutorFinalizer($this->createDeleteStatementExecutor($AST)),
        };
    }

    private function getReturningSql(): string
    {
        $returningClause = $this->getQuery()->getHint(self::RETURNING_CLAUSE);

        if (!$returningClause instanceof ReturningClause) {
            throw new ReturningWalkerException('Returning clause not provided');
        }

        return $returningClause->toSQL();
    }
}
