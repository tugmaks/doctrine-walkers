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
use Tugmaks\DoctrineWalkers\Tablesample\Tablesample;
use Tugmaks\DoctrineWalkers\Tablesample\TablesampleMethod;
use Tugmaks\DoctrineWalkers\Tablesample\TablesampleWalker;
use Tugmaks\DoctrineWalkersTest\Integration\Entity\Player;

/**
 * The sample is random, so only the fact that PostgreSQL accepts the query and returns a subset is checked.
 */
#[CoversClass(Tablesample::class)]
#[CoversClass(TablesampleWalker::class)]
final class TablesampleWalkerIntegrationTest extends IntegrationTestCase
{
    #[DataProvider('provideMethods')]
    public function testReturnsSubsetOfRows(TablesampleMethod $method): void
    {
        $players = [];

        for ($i = 1; 20 >= $i; ++$i) {
            $players[] = new Player('Player ' . $i, 'RU');
        }

        $this->persist(...$players);

        $query = $this->entityManager->createQuery(\sprintf('SELECT p.name FROM %s p', Player::class));
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, TablesampleWalker::class);
        $query->setHint(TablesampleWalker::TABLESAMPLE_RULE, [Player::class => new Tablesample($method, 50)]);

        self::assertLessThanOrEqual(20, \count($query->getArrayResult()));
    }

    /**
     * @return iterable<string, array{TablesampleMethod}>
     */
    public static function provideMethods(): iterable
    {
        yield 'BERNOULLI' => [TablesampleMethod::BERNOULLI];

        yield 'SYSTEM' => [TablesampleMethod::SYSTEM];
    }
}
