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
use PHPUnit\Framework\Attributes\CoversClass;
use Tugmaks\DoctrineWalkers\DistinctOn\DistinctOnWalker;
use Tugmaks\DoctrineWalkersTest\Integration\Entity\Player;

#[CoversClass(DistinctOnWalker::class)]
final class DistinctOnWalkerIntegrationTest extends IntegrationTestCase
{
    public function testKeepsFirstRowOfEachGroup(): void
    {
        $this->persist(
            new Player('Ivan', 'RU', 50),
            new Player('Oleg', 'RU', 90),
            new Player('Petr', 'RU', 70),
            new Player('Hans', 'DE', 80),
            new Player('Karl', 'DE', 60),
            new Player('Jean', 'FR', 40),
        );

        $query = $this->entityManager->createQuery(\sprintf(
            'SELECT p.country, p.name, p.score FROM %s p ORDER BY p.country ASC, p.score DESC',
            Player::class,
        ));
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, DistinctOnWalker::class);
        $query->setHint(DistinctOnWalker::DISTINCT_ON, ['p.country']);

        self::assertSame(
            [
                ['country' => 'DE', 'name' => 'Hans', 'score' => 80],
                ['country' => 'FR', 'name' => 'Jean', 'score' => 40],
                ['country' => 'RU', 'name' => 'Oleg', 'score' => 90],
            ],
            $query->getArrayResult(),
        );
    }
}
