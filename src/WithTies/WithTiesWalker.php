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

use Doctrine\ORM\Query\AST;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\Exec\SqlFinalizer;
use Doctrine\ORM\Query\OutputWalker;
use Doctrine\ORM\Query\SqlWalker;

final class WithTiesWalker extends SqlWalker implements OutputWalker
{
    public function getFinalizer(AST\DeleteStatement|AST\SelectStatement|AST\UpdateStatement $AST): SqlFinalizer
    {
        \assert($AST instanceof SelectStatement);

        return new Finalizer($this->createSqlForFinalizer($AST));
    }
}
