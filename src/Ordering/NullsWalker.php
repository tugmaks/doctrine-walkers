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

namespace Tugmaks\DoctrineWalkers\Ordering;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\SqlOutputWalker;

final class NullsWalker extends SqlOutputWalker
{
    public const NULLS_RULE = 'NullsWalker.Rule';

    /**
     * {@inheritDoc}
     */
    public function walkOrderByClause(Query\AST\OrderByClause $orderByClause): string
    {
        $hint = $this->getHint();

        if (null !== $hint) {
            $orderedItems = [];

            foreach ($orderByClause->orderByItems as $orderByItem) {
                $key = self::getOrderByKey($orderByItem);

                if (null !== $key) {
                    $orderedItems[] = $key;
                }
            }

            $unknownItems = \array_diff(\array_keys($hint), $orderedItems);

            if ([] !== $unknownItems) {
                throw new NullsWalkerException(\sprintf(
                    'NULLS FIRST/LAST hint refers to fields that are not used in ORDER BY: %s.',
                    \implode(', ', $unknownItems),
                ));
            }
        }

        return parent::walkOrderByClause($orderByClause);
    }

    /**
     * {@inheritDoc}
     */
    public function walkOrderByItem(Query\AST\OrderByItem $orderByItem): string
    {
        $key = self::getOrderByKey($orderByItem);
        $nulls = null === $key ? null : $this->getNULLS($key);
        $sql = parent::walkOrderByItem($orderByItem);

        if (null === $key || null === $nulls) {
            return $sql;
        }

        if (\is_string($orderByItem->expression) && \str_contains($sql, ',')) {
            throw new NullsWalkerException(\sprintf(
                'NULLS FIRST/LAST cannot be applied to "%s" because it is expanded into multiple columns.',
                $key,
            ));
        }

        return $sql . ' ' . $nulls->value;
    }

    /**
     * Returns "alias.field" for entity fields, the alias itself for result variables
     * (e.g. "SELECT (...) AS foo ... ORDER BY foo") and null for anything else.
     */
    private static function getOrderByKey(Query\AST\OrderByItem $orderByItem): ?string
    {
        $expression = $orderByItem->expression;

        if (\is_string($expression)) {
            return $expression;
        }

        if (
            !$expression instanceof Query\AST\PathExpression
            || Query\AST\PathExpression::TYPE_STATE_FIELD !== $expression->type
        ) {
            return null;
        }

        $field = $expression->field;

        if (null === $field) {
            throw new NullsWalkerException('PathExpression field cannot be null.');
        }

        return $expression->identificationVariable . '.' . $field;
    }

    /**
     * @return null|array<array-key, mixed>
     */
    private function getHint(): ?array
    {
        $hint = $this->getQuery()->getHint(self::NULLS_RULE);

        if (null === $hint || false === $hint) {
            return null;
        }

        if (!\is_array($hint)) {
            throw new NullsWalkerException('Hint for NullsWalker should be an array...');
        }

        return $hint;
    }

    private function getNULLS(string $name): ?NULLS
    {
        $nulls = $this->getHint()[$name] ?? null;

        if (null === $nulls) {
            return null;
        }

        if (!$nulls instanceof NULLS) {
            throw new NullsWalkerException('Hint for NULLS FIRST/LAST should be provided as NULLS enum case...');
        }

        return $nulls;
    }
}
