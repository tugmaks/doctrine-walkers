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

namespace Tugmaks\DoctrineWalkers\FilterWhere;

use Doctrine\ORM\Mapping\ToOneOwningSideMapping;
use Doctrine\ORM\Query\AST;
use Doctrine\ORM\Query\AST\Functions\AvgFunction;
use Doctrine\ORM\Query\AST\Functions\CountFunction;
use Doctrine\ORM\Query\AST\Functions\MaxFunction;
use Doctrine\ORM\Query\AST\Functions\MinFunction;
use Doctrine\ORM\Query\AST\Functions\SumFunction;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\Exec\SingleSelectSqlFinalizer;
use Doctrine\ORM\Query\Exec\SqlFinalizer;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\OutputWalker;
use Doctrine\ORM\Query\SqlWalker;

/**
 * Adds FILTER (WHERE ...) to aggregates selected under an alias.
 *
 * The hint maps a SELECT alias to a condition built with Doctrine\ORM\Query\Expr
 * ($qb->expr() or new Expr()). The aliased expression is an aggregate or COALESCE(...)
 * with exactly one aggregate as a direct argument.
 */
final class FilterWhereWalker extends SqlWalker implements OutputWalker
{
    public const FILTER_WHERE = 'FilterWhereWalker.FilterWhere';
    private const COMPARISON_OPERATORS = ['=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'];

    /**
     * Filters from the hint that have not been applied to a SELECT expression yet.
     *
     * @var array<array-key, mixed>
     */
    private array $pendingFilters = [];

    /**
     * Alias whose filter goes inside the COALESCE(...) of the SELECT expression being walked.
     */
    private ?string $coalesceAlias = null;

    public function walkSelectClause(AST\SelectClause $selectClause): string
    {
        $this->pendingFilters = $this->getFilters();

        $sql = parent::walkSelectClause($selectClause);

        if ([] !== $this->pendingFilters) {
            throw new FilterWhereWalkerException(\sprintf(
                'FILTER WHERE hint refers to alias "%s" that is not present in the SELECT clause.',
                \implode('", "', \array_keys($this->pendingFilters)),
            ));
        }

        return $sql;
    }

    public function walkSelectExpression(AST\SelectExpression $selectExpression): string
    {
        $alias = $selectExpression->fieldIdentificationVariable;

        if (null === $alias || !\array_key_exists($alias, $this->pendingFilters)) {
            return parent::walkSelectExpression($selectExpression);
        }

        if ($selectExpression->expression instanceof AST\CoalesceExpression) {
            self::assertSingleAggregateInCoalesce($selectExpression->expression, $alias);

            // walkCoalesceExpression() appends the filter to the aggregate inside COALESCE(...).
            $this->coalesceAlias = $alias;

            return parent::walkSelectExpression($selectExpression);
        }

        if (!self::isAggregate($selectExpression->expression)) {
            throw new FilterWhereWalkerException(\sprintf(
                'FILTER WHERE hint refers to alias "%s" that is not an aggregate expression.',
                $alias,
            ));
        }

        $sql = parent::walkSelectExpression($selectExpression);

        // The parent always renders "<expression> AS <column alias>", the filter goes right before " AS ".
        $position = \mb_strrpos($sql, ' AS ');
        \assert(false !== $position);

        return \mb_substr($sql, 0, $position) . $this->walkFilter($alias) . \mb_substr($sql, $position);
    }

    public function walkCoalesceExpression(AST\CoalesceExpression $coalesceExpression): string
    {
        $alias = $this->coalesceAlias;

        if (null === $alias) {
            return parent::walkCoalesceExpression($coalesceExpression);
        }

        // Apply the filter once: a COALESCE nested inside this one is rendered as is.
        $this->coalesceAlias = null;

        $parts = [];

        /** @var AST\Node|string $scalarExpression */
        foreach ($coalesceExpression->scalarExpressions as $scalarExpression) {
            $sql = $this->walkSimpleArithmeticExpression($scalarExpression);
            $parts[] = self::isAggregate($scalarExpression) ? $sql . $this->walkFilter($alias) : $sql;
        }

        return 'COALESCE(' . \implode(', ', $parts) . ')';
    }

    public function getFinalizer(AST\DeleteStatement|AST\SelectStatement|AST\UpdateStatement $AST): SqlFinalizer
    {
        \assert($AST instanceof SelectStatement);

        return new SingleSelectSqlFinalizer($this->createSqlForFinalizer($AST));
    }

    private static function isAggregate(mixed $expression): bool
    {
        return $expression instanceof AST\AggregateExpression
            || $expression instanceof CountFunction
            || $expression instanceof AvgFunction
            || $expression instanceof MaxFunction
            || $expression instanceof MinFunction
            || $expression instanceof SumFunction;
    }

    private static function assertSingleAggregateInCoalesce(AST\CoalesceExpression $coalesce, string $alias): void
    {
        $aggregates = \count(\array_filter($coalesce->scalarExpressions, self::isAggregate(...)));

        if (0 === $aggregates) {
            throw new FilterWhereWalkerException(\sprintf(
                'FILTER WHERE hint refers to alias "%s": COALESCE should have an aggregate as a direct argument, none found.',
                $alias,
            ));
        }

        if (1 < $aggregates) {
            throw new FilterWhereWalkerException(\sprintf(
                'FILTER WHERE hint refers to alias "%s": COALESCE should have exactly one aggregate as a direct argument, %d found.',
                $alias,
                $aggregates,
            ));
        }
    }

    /**
     * Renders " FILTER (WHERE ...)" for the alias and marks its filter as applied.
     */
    private function walkFilter(string $alias): string
    {
        $sql = ' FILTER (WHERE ' . $this->walkCondition($this->pendingFilters[$alias]) . ')';
        unset($this->pendingFilters[$alias]);

        return $sql;
    }

    private function walkCondition(mixed $condition): string
    {
        return match (true) {
            $condition instanceof Expr\Andx => $this->walkComposite($condition, ' AND '),
            $condition instanceof Expr\Orx => $this->walkComposite($condition, ' OR '),
            $condition instanceof Expr\Comparison => $this->walkComparison($condition),
            $condition instanceof Expr\Func => $this->walkFunc($condition),
            \is_string($condition) => $this->walkStringCondition($condition),
            default => throw new FilterWhereWalkerException(\sprintf(
                'FILTER WHERE condition of type "%s" is not supported.',
                \get_debug_type($condition),
            )),
        };
    }

    private function walkComposite(Expr\Andx|Expr\Orx $composite, string $separator): string
    {
        $parts = $composite->getParts();

        if ([] === $parts) {
            throw new FilterWhereWalkerException('FILTER WHERE condition should not be an empty andX() / orX().');
        }

        $sql = [];

        foreach ($parts as $part) {
            $partSql = $this->walkCondition($part);
            $isNested = ($part instanceof Expr\Andx || $part instanceof Expr\Orx) && $part->count() > 1;
            $sql[] = $isNested ? '(' . $partSql . ')' : $partSql;
        }

        return \implode($separator, $sql);
    }

    private function walkComparison(Expr\Comparison $comparison): string
    {
        $operator = $comparison->getOperator();

        if (!\in_array($operator, self::COMPARISON_OPERATORS, true)) {
            throw new FilterWhereWalkerException(\sprintf('FILTER WHERE comparison operator "%s" is not supported.', $operator));
        }

        return $this->walkOperand($comparison->getLeftExpr())
            . ' ' . $operator . ' '
            . $this->walkOperand($comparison->getRightExpr());
    }

    /**
     * Supports the functions produced by Expr::in(), Expr::notIn() and Expr::not().
     */
    private function walkFunc(Expr\Func $func): string
    {
        $name = $func->getName();
        $arguments = $func->getArguments();

        if ('NOT' === $name && 1 === \count($arguments)) {
            return 'NOT (' . $this->walkCondition(\reset($arguments)) . ')';
        }

        foreach ([' NOT IN', ' IN'] as $operator) {
            if (\str_ends_with($name, $operator) && [] !== $arguments) {
                $field = \mb_substr($name, 0, -\mb_strlen($operator));

                return $this->walkOperand($field) . $operator
                    . ' (' . \implode(', ', \array_map($this->walkOperand(...), $arguments)) . ')';
            }
        }

        throw new FilterWhereWalkerException(\sprintf('FILTER WHERE function "%s" is not supported.', $name));
    }

    /**
     * Supports the strings produced by Expr::isNull(), Expr::isNotNull() and Expr::between().
     */
    private function walkStringCondition(string $condition): string
    {
        foreach ([' IS NOT NULL', ' IS NULL'] as $suffix) {
            if (\str_ends_with($condition, $suffix)) {
                return $this->walkOperand(\mb_substr($condition, 0, -\mb_strlen($suffix))) . $suffix;
            }
        }

        $between = \explode(' BETWEEN ', $condition, 2);

        if (2 === \count($between)) {
            $bounds = \explode(' AND ', $between[1], 2);

            if (2 === \count($bounds)) {
                return $this->walkOperand($between[0])
                    . ' BETWEEN ' . $this->walkOperand($bounds[0])
                    . ' AND ' . $this->walkOperand($bounds[1]);
            }
        }

        throw new FilterWhereWalkerException(\sprintf(
            'FILTER WHERE condition "%s" is not supported, build it with Doctrine\ORM\Query\Expr.',
            $condition,
        ));
    }

    /**
     * An operand is a field path ("u.name"), a parameter (":name", "?1") or a literal (5, 'text', true).
     */
    private function walkOperand(mixed $operand): string
    {
        if ($operand instanceof Expr\Literal) {
            $parts = $operand->getParts();

            return $this->walkOperand(1 === \count($parts) ? \reset($parts) : null);
        }

        if (\is_int($operand) || \is_float($operand)) {
            return (string) $operand;
        }

        if (!\is_string($operand)) {
            throw new FilterWhereWalkerException(\sprintf(
                'FILTER WHERE operand of type "%s" is not supported.',
                \get_debug_type($operand),
            ));
        }

        $operand = \mb_trim($operand);

        if (\str_starts_with($operand, ':') || \str_starts_with($operand, '?')) {
            return $this->walkInputParameter(new AST\InputParameter($operand));
        }

        if (\is_numeric($operand) || 'true' === $operand || 'false' === $operand || self::isQuotedString($operand)) {
            return $operand;
        }

        $path = \explode('.', $operand);

        if (2 === \count($path)) {
            return $this->walkFieldPath($path[0], $path[1]);
        }

        throw new FilterWhereWalkerException(\sprintf('FILTER WHERE operand "%s" is not supported.', $operand));
    }

    /**
     * A string literal as produced by Expr::literal(): quoted, with inner quotes doubled.
     */
    private static function isQuotedString(string $operand): bool
    {
        return \mb_strlen($operand) >= 2
            && \str_starts_with($operand, "'")
            && \str_ends_with($operand, "'")
            && !\str_contains(\str_replace("''", '', \mb_substr($operand, 1, -1)), "'");
    }

    private function walkFieldPath(string $dqlAlias, string $field): string
    {
        if (!isset($this->getQueryComponents()[$dqlAlias])) {
            throw new FilterWhereWalkerException(\sprintf('FILTER WHERE condition refers to unknown alias "%s".', $dqlAlias));
        }

        $metadata = $this->getMetadataForDqlAlias($dqlAlias);
        $tableAlias = $this->getSQLTableAlias($metadata->getTableName(), $dqlAlias);

        if ($metadata->hasField($field)) {
            return $tableAlias . '.' . $metadata->getColumnName($field);
        }

        if (!$metadata->hasAssociation($field)) {
            throw new FilterWhereWalkerException(\sprintf('FILTER WHERE condition refers to unknown field "%s.%s".', $dqlAlias, $field));
        }

        // An association is compared by its foreign key column, which only a to-one owning side with one join column has.
        $association = $metadata->getAssociationMapping($field);

        if (!$association instanceof ToOneOwningSideMapping || 1 !== \count($association->joinColumns)) {
            throw new FilterWhereWalkerException(\sprintf(
                'FILTER WHERE condition refers to association "%s.%s" that is not supported: only to-one owning side associations with a single join column can be used.',
                $dqlAlias,
                $field,
            ));
        }

        return $tableAlias . '.' . $association->joinColumns[0]->name;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getFilters(): array
    {
        $hint = $this->getQuery()->getHint(self::FILTER_WHERE);

        if (null === $hint || false === $hint) {
            return [];
        }

        if (!\is_array($hint)) {
            throw new FilterWhereWalkerException('Hint for FilterWhereWalker should be an array of "alias => condition" pairs.');
        }

        return $hint;
    }
}
