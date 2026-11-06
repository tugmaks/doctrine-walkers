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
use Tugmaks\DoctrineWalkers\Ordering\NULLS;
use Tugmaks\DoctrineWalkers\Ordering\NullsWalker;
use Tugmaks\DoctrineWalkersTest\Integration\Entity\Player;

#[CoversClass(NullsWalker::class)]
final class NullsWalkerIntegrationTest extends IntegrationTestCase
{
    /**
     * PostgreSQL puts NULL last for ASC and first for DESC, so each case checks the opposite.
     *
     * @param list<string> $expectedNames
     */
    #[DataProvider('provideOrderings')]
    public function testOverridesDefaultNullsPosition(string $direction, NULLS $nulls, array $expectedNames): void
    {
        $this->persist(
            new Player('A', 'RU', 10),
            new Player('B', 'RU'),
            new Player('C', 'RU', 30),
        );

        $query = $this->entityManager->createQuery(\sprintf(
            'SELECT p.name FROM %s p ORDER BY p.score %s',
            Player::class,
            $direction,
        ));
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
        $query->setHint(NullsWalker::NULLS_RULE, ['p.score' => $nulls]);

        self::assertSame(
            \array_map(static fn (string $name): array => ['name' => $name], $expectedNames),
            $query->getArrayResult(),
        );
    }

    /**
     * @return iterable<string, array{string, NULLS, list<string>}>
     */
    public static function provideOrderings(): iterable
    {
        yield 'ASC NULLS FIRST' => ['ASC', NULLS::FIRST, ['B', 'A', 'C']];

        yield 'DESC NULLS LAST' => ['DESC', NULLS::LAST, ['C', 'A', 'B']];
    }
}
