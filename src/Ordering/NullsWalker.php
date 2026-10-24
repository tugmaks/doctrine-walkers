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
            $orderedFields = [];

            foreach ($orderByClause->orderByItems as $orderByItem) {
                $name = self::getStateFieldName($orderByItem);

                if (null !== $name) {
                    $orderedFields[] = $name;
                }
            }

            $unknownFields = \array_diff(\array_keys($hint), $orderedFields);

            if ([] !== $unknownFields) {
                throw new NullsWalkerException(\sprintf(
                    'NULLS FIRST/LAST hint refers to fields that are not used in ORDER BY: %s.',
                    \implode(', ', $unknownFields),
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
        $name = self::getStateFieldName($orderByItem);

        if (null !== $name && $orderByItem->expression instanceof Query\AST\PathExpression) {
            $nulls = $this->getNULLS($name);

            if (null !== $nulls) {
                return $this->walkPathExpression($orderByItem->expression) . ' ' . $orderByItem->type . ' ' . $nulls->value;
            }
        }

        return parent::walkOrderByItem($orderByItem);
    }

    private static function getStateFieldName(Query\AST\OrderByItem $orderByItem): ?string
    {
        if (
            !$orderByItem->expression instanceof Query\AST\PathExpression
            || Query\AST\PathExpression::TYPE_STATE_FIELD !== $orderByItem->expression->type
        ) {
            return null;
        }

        $field = $orderByItem->expression->field;

        if (null === $field) {
            throw new NullsWalkerException('PathExpression field cannot be null.');
        }

        return $orderByItem->expression->identificationVariable . '.' . $field;
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
