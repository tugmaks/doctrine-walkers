<?php

declare(strict_types=1);

/**
 * Copyright (c) 2025 Maksim Tugaev
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/tugmaks/doctrine-walkers
 */

namespace Tugmaks\DoctrineWalkersTest\Tablesample;

use Doctrine\ORM\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tugmaks\DoctrineWalkers\Ordering\NULLS;
use Tugmaks\DoctrineWalkers\Tablesample\Tablesample;
use Tugmaks\DoctrineWalkers\Tablesample\TablesampleMethod;
use Tugmaks\DoctrineWalkers\Tablesample\TablesampleWalker;
use Tugmaks\DoctrineWalkers\Tablesample\TablesampleWalkerException;
use Tugmaks\DoctrineWalkersTest\AbstractWalkerTestCase;
use Tugmaks\DoctrineWalkersTest\DummyEntity;

#[CoversClass(Tablesample::class)]
#[CoversClass(TablesampleWalker::class)]
final class TablesampleWalkerTest extends AbstractWalkerTestCase
{
    /**
     * @param array<string, NULLS> $hint
     */
    #[DataProvider('tablesamples')]
    public function testTablesampleWalker(string $dql, array $hint, string $sql): void
    {
        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, TablesampleWalker::class);
        $query->setHint(TablesampleWalker::TABLESAMPLE_RULE, $hint);

        self::assertSame($sql, $query->getSQL());
    }

    /**
     * @return iterable<string, array{0:string, 1: array<class-string, Tablesample>, 2:string}>
     */
    public static function tablesamples(): iterable
    {
        yield 'BERNOULLI(0.1)' => [
            \sprintf('SELECT d FROM %s d ORDER BY d.name DESC', DummyEntity::class),
            [DummyEntity::class => new Tablesample(TablesampleMethod::BERNOULLI, 0.1)],
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_ TABLESAMPLE BERNOULLI(0.1) ORDER BY d0_.name DESC',
        ];

        yield 'SYSTEM(0.2)' => [
            \sprintf('SELECT d FROM %s d ORDER BY d.name DESC', DummyEntity::class),
            [DummyEntity::class => new Tablesample(TablesampleMethod::SYSTEM, 0.2)],
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_ TABLESAMPLE SYSTEM(0.2) ORDER BY d0_.name DESC',
        ];
    }

    #[DataProvider('invalidPercentage')]
    public function testItThrowExceptionOnInvalidPercentage(float $percentage): void
    {
        self::expectException(TablesampleWalkerException::class);
        self::expectExceptionMessage('A percentage must be between 0 and 100');

        $dql = \sprintf('SELECT d FROM %s d ORDER BY d.name DESC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, Tablesample::class);
        $query->setHint(TablesampleWalker::TABLESAMPLE_RULE, [DummyEntity::class => new Tablesample(TablesampleMethod::SYSTEM, $percentage)]);

        $query->getSQL();
    }

    /**
     * @return iterable<int, list<float>>
     */
    public static function invalidPercentage(): iterable
    {
        yield [-1.00];

        yield [101.00];
    }
}
