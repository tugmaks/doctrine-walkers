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

namespace Tugmaks\DoctrineWalkersTest\Integration;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use PHPUnit\Framework\Attributes\CoversClass;
use Tugmaks\DoctrineWalkers\FilterWhere\FilterWhereWalker;
use Tugmaks\DoctrineWalkersTest\Integration\Entity\Player;
use Tugmaks\DoctrineWalkersTest\Integration\Entity\Team;

#[CoversClass(FilterWhereWalker::class)]
final class FilterWhereWalkerIntegrationTest extends IntegrationTestCase
{
    public function testComputesFilteredAggregates(): void
    {
        $red = new Team('Red');
        $blue = new Team('Blue');

        $this->persist(
            $red,
            $blue,
            new Player('Ivan', 'RU', 50, $red),
            new Player('Oleg', 'RU', 90, $red),
            new Player('Hans', 'DE', 80, $blue),
            new Player('Karl', 'FR', null, $blue),
            new Player('Jean', 'DE', 40),
        );

        $expr = new Expr();
        $query = $this->entityManager->createQuery(\sprintf(
            'SELECT COUNT(p.id) AS total,'
            . ' COUNT(p.id) AS strong,'
            . ' COUNT(p.id) AS red_members,'
            . ' COALESCE(SUM(p.score), 0) AS weak_score,'
            . ' COALESCE(SUM(p.score), 0) AS no_match_score,'
            . ' COUNT(DISTINCT p.country) AS scored_countries'
            . ' FROM %s p',
            Player::class,
        ));
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);
        $query->setHint(FilterWhereWalker::FILTER_WHERE, [
            'strong' => $expr->gte('p.score', ':min'),
            'red_members' => $expr->eq('p.team', ':team'),
            'weak_score' => $expr->andX($expr->lt('p.score', 60), $expr->isNotNull('p.score')),
            'no_match_score' => $expr->gt('p.score', 1000),
            'scored_countries' => $expr->isNotNull('p.score'),
        ]);
        $query->setParameter('min', 80);
        $query->setParameter('team', $red->id);

        self::assertSame(
            [
                'total' => 5,
                'strong' => 2,
                'red_members' => 2,
                'weak_score' => 90,
                'no_match_score' => 0,
                'scored_countries' => 2,
            ],
            $query->getSingleResult(),
        );
    }
}
