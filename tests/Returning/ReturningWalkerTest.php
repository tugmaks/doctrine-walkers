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

namespace Tugmaks\DoctrineWalkersTest\Returning;

use Doctrine\ORM\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tugmaks\DoctrineWalkers\Returning\ReturningClause;
use Tugmaks\DoctrineWalkers\Returning\ReturningWalker;
use Tugmaks\DoctrineWalkers\Returning\ReturningWalkerException;
use Tugmaks\DoctrineWalkersTest\AbstractWalkerTestCase;
use Tugmaks\DoctrineWalkersTest\DummyEntity;

#[CoversClass(ReturningClause::class)]
#[CoversClass(ReturningWalker::class)]
final class ReturningWalkerTest extends AbstractWalkerTestCase
{
    #[DataProvider('selectQueries')]
    public function testSelect(ReturningClause $returningClause, string $producedSql): void
    {
        $dql = \sprintf('SELECT d FROM %s d WHERE d.id = 1', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);
        $query->setHint(ReturningWalker::RETURNING_CLAUSE, $returningClause);

        self::assertSame($producedSql, $query->getSQL());
    }

    /**
     * @return iterable<array{0:ReturningClause, 1:string}>
     */
    public static function selectQueries(): iterable
    {
        yield 'Return all columns' => [
            new ReturningClause(),
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_ WHERE d0_.id = 1 RETURNING *',
        ];

        yield 'Return specific columns' => [
            new ReturningClause(['id', 'name']),
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_ WHERE d0_.id = 1 RETURNING id, name',
        ];
    }

    #[DataProvider('updateQueries')]
    public function testUpdate(ReturningClause $returningClause, string $producedSql): void
    {
        $dql = \sprintf('UPDATE %s d SET d.name = ?1 WHERE d.id = ?2', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);
        $query->setParameter(1, 'test');
        $query->setParameter(2, 1);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);
        $query->setHint(ReturningWalker::RETURNING_CLAUSE, $returningClause);

        self::assertSame($producedSql, $query->getSQL());
    }

    /**
     * @return iterable<array{0:ReturningClause, 1:string}>
     */
    public static function updateQueries(): iterable
    {
        yield 'Return all columns' => [
            new ReturningClause(),
            'UPDATE de_tbl SET name = ? WHERE id = ? RETURNING *',
        ];

        yield 'Return specific columns' => [
            new ReturningClause(['id', 'name']),
            'UPDATE de_tbl SET name = ? WHERE id = ? RETURNING id, name',
        ];
    }

    #[DataProvider('deleteQueries')]
    public function testDelete(ReturningClause $returningClause, string $producedSql): void
    {
        $dql = \sprintf('DELETE %s d WHERE d.id = ?1', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);
        $query->setParameter(1, 1);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);
        $query->setHint(ReturningWalker::RETURNING_CLAUSE, $returningClause);

        self::assertSame($producedSql, $query->getSQL());
    }

    /**
     * @return iterable<array{0:ReturningClause, 1:string}>
     */
    public static function deleteQueries(): iterable
    {
        yield 'Return all columns' => [
            new ReturningClause(),
            'DELETE FROM de_tbl WHERE id = ? RETURNING *',
        ];

        yield 'Return specific columns' => [
            new ReturningClause(['id', 'name']),
            'DELETE FROM de_tbl WHERE id = ? RETURNING id, name',
        ];
    }

    #[DataProvider('selectQueries')]
    public function testSelectWithQueryBuilder(ReturningClause $returningClause, string $producedSql): void
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(DummyEntity::class, 'd')
            ->where('d.id = 1')
            ->getQuery();

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);
        $query->setHint(ReturningWalker::RETURNING_CLAUSE, $returningClause);

        self::assertSame($producedSql, $query->getSQL());
    }

    #[DataProvider('updateQueries')]
    public function testUpdateWithQueryBuilder(ReturningClause $returningClause, string $producedSql): void
    {
        $query = $this->entityManager->createQueryBuilder()
            ->update(DummyEntity::class, 'd')
            ->set('d.name', ':name')
            ->where('d.id = :id')
            ->setParameter('name', 'test')
            ->setParameter('id', 1)
            ->getQuery();

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);
        $query->setHint(ReturningWalker::RETURNING_CLAUSE, $returningClause);

        self::assertSame($producedSql, $query->getSQL());
    }

    #[DataProvider('deleteQueries')]
    public function testDeleteWithQueryBuilder(ReturningClause $returningClause, string $producedSql): void
    {
        $query = $this->entityManager->createQueryBuilder()
            ->delete(DummyEntity::class, 'd')
            ->where('d.id = :id')
            ->setParameter('id', 1)
            ->getQuery();

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);
        $query->setHint(ReturningWalker::RETURNING_CLAUSE, $returningClause);

        self::assertSame($producedSql, $query->getSQL());
    }

    public function testThrowsExceptionIfReturningClauseNotProvidedOnSelect(): void
    {
        self::expectException(ReturningWalkerException::class);
        self::expectExceptionMessage('Returning clause not provided');

        $dql = \sprintf('SELECT d FROM %s d WHERE d.id = 1', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);

        $query->getSQL();
    }
}
