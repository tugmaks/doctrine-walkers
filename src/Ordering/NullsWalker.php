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

namespace Tugmaks\DoctrineWalkers\Ordering;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\SqlWalker;

final class NullsWalker extends SqlWalker
{
    public const NULLS_RULE = 'NullsWalker.Rule';

    /**
     * {@inheritDoc}
     */
    public function walkOrderByItem($orderByItem): string
    {
        if (
            $orderByItem->expression instanceof Query\AST\PathExpression
            && Query\AST\PathExpression::TYPE_STATE_FIELD === $orderByItem->expression->type
        ) {
            $name = $orderByItem->expression->identificationVariable . '.' . $orderByItem->expression->field;
            $nulls = $this->getNULLS($name);

            if (null !== $nulls) {
                return $this->walkPathExpression($orderByItem->expression) . ' ' . $orderByItem->type . ' ' . $nulls->value;
            }
        }

        return parent::walkOrderByItem($orderByItem);
    }

    private function getNULLS(string $name): ?NULLS
    {
        $hint = $this->getQuery()->getHint(self::NULLS_RULE);

        if (!\is_array($hint)) {
            throw new NullsWalkerException('Hint for NullsWalker should be an array...');
        }

        $nulls = $hint[$name] ?? null;

        if (null === $nulls) {
            return null;
        }

        if (!$nulls instanceof NULLS) {
            throw new NullsWalkerException('Hint for NULLS FIRST/LAST should be provided as NULLS enum case...');
        }

        return $nulls;
    }
}
