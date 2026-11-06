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

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\AST;
use Doctrine\ORM\Query\AST\DeleteStatement;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\AST\UpdateStatement;
use Doctrine\ORM\Query\Exec\PreparedExecutorFinalizer;
use Doctrine\ORM\Query\Exec\SqlFinalizer;
use Doctrine\ORM\Query\OutputWalker;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\ParserResult;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Utility\PersisterHelper;

/**
 * @phpstan-import-type QueryComponent from Parser
 */
final class ReturningWalker extends SqlWalker implements OutputWalker
{
    public const RETURNING_CLAUSE = 'ReturningWalker.ReturningClause';
    private readonly ResultSetMapping $resultSetMapping;

    /**
     * @param array<string, QueryComponent> $queryComponents
     */
    public function __construct(Query $query, ParserResult $parserResult, array $queryComponents)
    {
        parent::__construct($query, $parserResult, $queryComponents);

        // The same mapping Doctrine later uses to hydrate the rows returned by ReturningExecutor.
        $this->resultSetMapping = $parserResult->getResultSetMapping();
    }

    public function walkUpdateStatement(AST\UpdateStatement $updateStatement): string
    {
        return \sprintf(
            '%s %s',
            parent::walkUpdateStatement($updateStatement),
            $this->getReturningClause()->toSQL(),
        );
    }

    public function walkDeleteStatement(AST\DeleteStatement $deleteStatement): string
    {
        return \sprintf(
            '%s %s',
            parent::walkDeleteStatement($deleteStatement),
            $this->getReturningClause()->toSQL(),
        );
    }

    public function getFinalizer(DeleteStatement|SelectStatement|UpdateStatement $AST): SqlFinalizer
    {
        if ($AST instanceof SelectStatement) {
            throw new ReturningWalkerException('RETURNING is supported only for UPDATE and DELETE statements, not for SELECT');
        }

        $class = $this->getEntityManager()->getClassMetadata(
            $AST instanceof UpdateStatement
                ? $AST->updateClause->abstractSchemaName
                : $AST->deleteClause->abstractSchemaName,
        );

        if ($class->isInheritanceTypeJoined()) {
            // Doctrine runs such statements as several queries over a temporary table.
            throw new ReturningWalkerException('RETURNING is not supported for entities with JOINED inheritance');
        }

        $sql = $AST instanceof UpdateStatement ? $this->walkUpdateStatement($AST) : $this->walkDeleteStatement($AST);

        $this->mapReturnedColumns($class);

        return new PreparedExecutorFinalizer(new ReturningExecutor($sql));
    }

    /**
     * Each returned column becomes a scalar result named after the column,
     * converted to PHP by the type of the mapped field or association.
     *
     * @param ClassMetadata<object> $class
     */
    private function mapReturnedColumns(ClassMetadata $class): void
    {
        $columns = $this->getReturningClause()->getColumns();

        if ('*' === $columns) {
            $columns = self::getTableColumns($class);
        } elseif (\is_string($columns)) {
            $columns = self::splitColumnList($columns);
        }

        foreach ($columns as $column) {
            // "expression AS alias": the row key is the alias, the value is returned as the driver gives it.
            if (1 === \preg_match('/\sAS\s+(\w+)$/i', $column, $matches)) {
                $this->resultSetMapping->addScalarResult($matches[1], $matches[1]);

                continue;
            }

            try {
                $type = PersisterHelper::getTypeOfColumn($column, $class, $this->getEntityManager());
            } catch (\RuntimeException) {
                // Not a mapped column, so there is no type to convert with.
                $type = 'string';
            }

            $this->resultSetMapping->addScalarResult($column, $column, $type);
        }
    }

    /**
     * Splits "id, COALESCE(score, 0) AS score" by commas outside parentheses and string literals.
     *
     * @return list<string>
     */
    private static function splitColumnList(string $columnList): array
    {
        $columns = [];
        $current = '';
        $depth = 0;
        $inLiteral = false;

        foreach (\mb_str_split($columnList) as $char) {
            if ("'" === $char) {
                $inLiteral = !$inLiteral;
            } elseif (!$inLiteral && '(' === $char) {
                ++$depth;
            } elseif (!$inLiteral && ')' === $char) {
                --$depth;
            } elseif (!$inLiteral && 0 === $depth && ',' === $char) {
                $columns[] = \mb_trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $columns[] = \mb_trim($current);

        return $columns;
    }

    /**
     * @param ClassMetadata<object> $class
     *
     * @return list<string>
     */
    private static function getTableColumns(ClassMetadata $class): array
    {
        $columns = $class->getColumnNames();

        foreach ($class->associationMappings as $association) {
            if (!$association->isToOneOwningSide()) {
                continue;
            }

            foreach ($association->joinColumns as $joinColumn) {
                $columns[] = $joinColumn->name;
            }
        }

        return $columns;
    }

    private function getReturningClause(): ReturningClause
    {
        $returningClause = $this->getQuery()->getHint(self::RETURNING_CLAUSE);

        if (!$returningClause instanceof ReturningClause) {
            throw new ReturningWalkerException('Returning clause not provided');
        }

        return $returningClause;
    }
}
