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

namespace Tugmaks\DoctrineWalkersTest\DistinctOn;

use Doctrine\ORM\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tugmaks\DoctrineWalkers\DistinctOn\DistinctOnWalker;
use Tugmaks\DoctrineWalkers\DistinctOn\DistinctOnWalkerException;
use Tugmaks\DoctrineWalkersTest\AbstractWalkerTestCase;
use Tugmaks\DoctrineWalkersTest\DummyEntity;

#[CoversClass(DistinctOnWalker::class)]
final class DistinctOnWalkerTest extends AbstractWalkerTestCase
{
    /**
     * @param array<int, string> $distinctOn
     */
    #[DataProvider('distinctOnAndSql')]
    public function testHints(array $distinctOn, string $producedSql): void
    {
        $dql = \sprintf('SELECT d FROM %s d ORDER BY d.iq DESC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, DistinctOnWalker::class);
        $query->setHint(DistinctOnWalker::DISTINCT_ON, $distinctOn);

        self::assertSame($producedSql, $query->getSQL());
    }

    /**
     * @return iterable<array{0: array<int, string>, 1: string}>
     */
    public static function distinctOnAndSql(): iterable
    {
        yield 'Single column' => [
            ['d.iq'],
            'SELECT DISTINCT ON (d0_.iq) d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_ ORDER BY d0_.iq DESC',
        ];

        yield 'Multiple columns' => [
            ['d.iq', 'd.name'],
            'SELECT DISTINCT ON (d0_.iq, d0_.name) d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_ ORDER BY d0_.iq DESC',
        ];
    }

    /**
     * @param array<int, string> $distinctOn
     */
    #[DataProvider('distinctOnAndSql')]
    public function testWithQueryBuilder(array $distinctOn, string $producedSql): void
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(DummyEntity::class, 'd')
            ->orderBy('d.iq', 'DESC')
            ->getQuery();

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, DistinctOnWalker::class);
        $query->setHint(DistinctOnWalker::DISTINCT_ON, $distinctOn);

        self::assertSame($producedSql, $query->getSQL());
    }

    public function testItThrowsExceptionIfDqlHasDistinct(): void
    {
        $this->expectException(DistinctOnWalkerException::class);
        $this->expectExceptionMessage('DISTINCT ON is not compatible with SELECT DISTINCT in the same query');

        $dql = \sprintf('SELECT DISTINCT d FROM %s d ORDER BY d.iq DESC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, DistinctOnWalker::class);
        $query->setHint(DistinctOnWalker::DISTINCT_ON, ['d.iq']);

        $query->getSQL();
    }

    public function testItThrowsExceptionIfDistinctOnNotProvided(): void
    {
        self::expectException(DistinctOnWalkerException::class);
        self::expectExceptionMessage('DISTINCT ON hint must be a non-empty array of field paths');

        $dql = \sprintf('SELECT d FROM %s d ORDER BY d.iq DESC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, DistinctOnWalker::class);

        $query->getSQL();
    }

    public function testItThrowsExceptionIfExpressionIsNotString(): void
    {
        $this->expectException(DistinctOnWalkerException::class);
        $this->expectExceptionMessage('DISTINCT ON expressions must be strings');

        $dql = \sprintf('SELECT d FROM %s d ORDER BY d.iq DESC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, DistinctOnWalker::class);
        $query->setHint(DistinctOnWalker::DISTINCT_ON, ['d.iq', 42]);

        $query->getSQL();
    }

    public function testItThrowsExceptionIfExpressionIsInvalidFormat(): void
    {
        $this->expectException(DistinctOnWalkerException::class);
        $this->expectExceptionMessage('must be in the format "alias.field"');

        $dql = \sprintf('SELECT d FROM %s d ORDER BY d.iq DESC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, DistinctOnWalker::class);
        $query->setHint(DistinctOnWalker::DISTINCT_ON, ['invalid']);

        $query->getSQL();
    }
}
