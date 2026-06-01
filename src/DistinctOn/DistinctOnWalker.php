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

namespace Tugmaks\DoctrineWalkers\DistinctOn;

use Doctrine\ORM\Query\AST;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\Exec\SingleSelectSqlFinalizer;
use Doctrine\ORM\Query\Exec\SqlFinalizer;
use Doctrine\ORM\Query\OutputWalker;
use Doctrine\ORM\Query\SqlWalker;

final class DistinctOnWalker extends SqlWalker implements OutputWalker
{
    public const DISTINCT_ON = 'DistinctOnWalker.DistinctOn';

    public function walkSelectStatement(AST\SelectStatement $selectStatement): string
    {
        if ($selectStatement->selectClause->isDistinct) {
            throw new DistinctOnWalkerException('DISTINCT ON is not compatible with SELECT DISTINCT in the same query');
        }

        $distinctOn = $this->getQuery()->getHint(self::DISTINCT_ON);

        if (!\is_array($distinctOn) || [] === $distinctOn) {
            throw new DistinctOnWalkerException('DISTINCT ON hint must be a non-empty array of field paths');
        }

        $sql = $this->createSqlForFinalizer($selectStatement);

        $columns = [];

        foreach ($distinctOn as $expr) {
            if (!\is_string($expr)) {
                throw new DistinctOnWalkerException('DISTINCT ON expressions must be strings');
            }

            $columns[] = $this->resolveFieldPath($expr);
        }

        return 'SELECT DISTINCT ON (' . \implode(', ', $columns) . ') ' . \mb_substr($sql, 7);
    }

    public function getFinalizer(AST\DeleteStatement|AST\SelectStatement|AST\UpdateStatement $AST): SqlFinalizer
    {
        \assert($AST instanceof SelectStatement);

        return new SingleSelectSqlFinalizer($this->walkSelectStatement($AST));
    }

    private function resolveFieldPath(string $expression): string
    {
        $parts = \explode('.', $expression);

        if (2 !== \count($parts)) {
            throw new DistinctOnWalkerException(\sprintf('DISTINCT ON expression "%s" must be in the format "alias.field"', $expression));
        }

        [$alias, $field] = $parts;

        $metadata = $this->getMetadataForDqlAlias($alias);
        $columnName = $metadata->getColumnName($field);

        return $this->getSQLTableAlias($metadata->getTableName(), $alias) . '.' . $columnName;
    }
}
