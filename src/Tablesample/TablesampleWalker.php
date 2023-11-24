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

namespace Tugmaks\DoctrineWalkers\Tablesample;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\SqlWalker;

final class TablesampleWalker extends SqlWalker
{
    public const TABLESAMPLE_RULE = 'TablesampleWalker.Rule';

    /**
     * @param \Doctrine\ORM\Query\AST\FromClause $fromClause
     *
     * @throws Query\QueryException
     */
    public function walkFromClause($fromClause): string
    {
        /** @var array<\Doctrine\ORM\Query\AST\IdentificationVariableDeclaration> $identificationVarDecls */
        $identificationVarDecls = $fromClause->identificationVariableDeclarations;
        $sqlParts = [];

        /** @var array<class-string, Tablesample> $hint */
        $hint = $this->getQuery()->getHint(self::TABLESAMPLE_RULE);

        foreach ($identificationVarDecls as $identificationVariableDecl) {
            $sqlPart = $this->walkIdentificationVariableDeclaration($identificationVariableDecl);
            $candidate = $hint[$identificationVariableDecl->rangeVariableDeclaration?->abstractSchemaName] ?? null;

            if ($candidate instanceof Tablesample) {
                $sqlPart .= ' ' . $candidate->toSQL();
            }

            $sqlParts[] = $sqlPart;
        }

        return ' FROM ' . \implode(', ', $sqlParts);
    }
}
