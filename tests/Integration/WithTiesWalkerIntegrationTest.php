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
use PHPUnit\Framework\Attributes\DataProvider;
use Tugmaks\DoctrineWalkers\WithTies\Finalizer;
use Tugmaks\DoctrineWalkers\WithTies\WithTiesWalker;
use Tugmaks\DoctrineWalkersTest\Integration\Entity\Player;

#[CoversClass(Finalizer::class)]
#[CoversClass(WithTiesWalker::class)]
final class WithTiesWalkerIntegrationTest extends IntegrationTestCase
{
    /**
     * @param list<int> $expectedScores
     */
    #[DataProvider('providePages')]
    public function testIncludesRowsTiedWithLastOne(int $offset, int $limit, array $expectedScores): void
    {
        $this->persist(
            new Player('A', 'RU', 10),
            new Player('B', 'RU', 9),
            new Player('C', 'RU', 9),
            new Player('D', 'RU', 9),
            new Player('E', 'RU', 8),
        );

        $query = $this->entityManager->createQuery(\sprintf('SELECT p.score FROM %s p ORDER BY p.score DESC', Player::class))
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, WithTiesWalker::class);

        self::assertSame(
            \array_map(static fn (int $score): array => ['score' => $score], $expectedScores),
            $query->getArrayResult(),
        );
    }

    /**
     * @return iterable<string, array{int, int, list<int>}>
     */
    public static function providePages(): iterable
    {
        yield 'first page' => [0, 2, [10, 9, 9, 9]];

        yield 'with offset' => [1, 1, [9, 9, 9]];
    }
}
