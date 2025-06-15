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

namespace Tugmaks\DoctrineWalkersTest\Locking;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use Tugmaks\DoctrineWalkers\Locking\LockingClause;
use Tugmaks\DoctrineWalkers\Locking\LockingWalker;
use Tugmaks\DoctrineWalkers\Locking\LockingWalkerException;
use Tugmaks\DoctrineWalkers\Locking\LockStrength;
use Tugmaks\DoctrineWalkers\Locking\Option;
use Tugmaks\DoctrineWalkersTest\AbstractWalkerTestCase;
use Tugmaks\DoctrineWalkersTest\DummyEntity;

#[CoversClass(LockingClause::class)]
#[CoversMethod(LockingWalker::class, 'getFinalizer')]
#[CoversMethod(LockingWalker::class, 'walkSelectStatement')]
final class LockingWalkerTest extends AbstractWalkerTestCase
{
    #[DataProvider('lockingClauseAndSql')]
    public function testHints(LockingClause $lockingClause, string $producedSql): void
    {
        $dql = \sprintf('SELECT d FROM %s d WHERE d.id = 1', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, LockingWalker::class);
        $query->setHint(LockingWalker::LOCKING_CLAUSE, $lockingClause);

        self::assertSame($producedSql, $query->getSQL());
    }

    /**
     * @return iterable<array{0:LockingClause, 1:string}>
     */
    public static function lockingClauseAndSql(): iterable
    {
        yield [
            new LockingClause(LockStrength::UPDATE),
            'SELECT d0_.id AS id_0, d0_.name AS name_1 FROM de_tbl d0_ WHERE d0_.id = 1 FOR UPDATE',
        ];

        yield [
            new LockingClause(LockStrength::UPDATE, Option::SKIP_LOCKED),
            'SELECT d0_.id AS id_0, d0_.name AS name_1 FROM de_tbl d0_ WHERE d0_.id = 1 FOR UPDATE SKIP LOCKED',
        ];
    }

    public function testItThrowsExceptionIfLockingClauseNotProvided(): void
    {
        self::expectException(LockingWalkerException::class);
        self::expectExceptionMessage('Locking clause not provided');

        $dql = \sprintf('SELECT d FROM %s d WHERE d.id = 1', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, LockingWalker::class);

        $query->getSQL();
    }

    public function testItThrowsExceptionIfAnotherLockSet(): void
    {
        self::expectException(LockingWalkerException::class);
        self::expectExceptionMessage('Query is already marked for locking...');

        $dql = \sprintf('SELECT d FROM %s d WHERE d.id = 1', DummyEntity::class);

        $this->connectionMock->method('isTransactionActive')->willReturn(true);
        $em = new EntityManager($this->connectionMock, $this->configuration);

        $query = $em->createQuery($dql)->setLockMode(LockMode::PESSIMISTIC_READ);
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, LockingWalker::class);
        $query->setHint(LockingWalker::LOCKING_CLAUSE, new LockingClause(LockStrength::UPDATE));

        $query->getSQL();
    }
}
