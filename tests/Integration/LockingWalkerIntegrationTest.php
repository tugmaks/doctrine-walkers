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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use Tugmaks\DoctrineWalkers\Locking\LockingClause;
use Tugmaks\DoctrineWalkers\Locking\LockingWalker;
use Tugmaks\DoctrineWalkers\Locking\LockStrength;
use Tugmaks\DoctrineWalkers\Locking\Option;
use Tugmaks\DoctrineWalkersTest\Integration\Entity\Player;

#[CoversClass(LockingClause::class)]
#[CoversClass(LockingWalker::class)]
final class LockingWalkerIntegrationTest extends IntegrationTestCase
{
    private Connection $lockHolder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->persist(new Player('A', 'RU'), new Player('B', 'RU'), new Player('C', 'RU'));

        // Another session locks row "B" until tearDown.
        $this->lockHolder = self::createConnection();
        $this->lockHolder->beginTransaction();
        $this->lockHolder->executeQuery("SELECT id FROM it_players WHERE name = 'B' FOR UPDATE");
    }

    protected function tearDown(): void
    {
        if (isset($this->lockHolder)) {
            $this->lockHolder->rollBack();
            $this->lockHolder->close();
        }

        parent::tearDown();
    }

    public function testSkipLockedSkipsRowsLockedByAnotherSession(): void
    {
        $query = $this->createLockingQuery(Option::SKIP_LOCKED);

        self::assertSame([['name' => 'A'], ['name' => 'C']], $query->getArrayResult());
    }

    public function testNowaitFailsOnRowLockedByAnotherSession(): void
    {
        $query = $this->createLockingQuery(Option::NOWAIT);

        try {
            $query->getArrayResult();
            self::fail('Expected NOWAIT to fail on a locked row.');
        } catch (DriverException $exception) {
            self::assertSame('55P03', $exception->getSQLState(), 'lock_not_available');
        }
    }

    private function createLockingQuery(Option $option): Query
    {
        $query = $this->entityManager->createQuery(\sprintf('SELECT p.name FROM %s p ORDER BY p.id', Player::class));
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, LockingWalker::class);
        $query->setHint(LockingWalker::LOCKING_CLAUSE, new LockingClause(LockStrength::UPDATE, $option));

        return $query;
    }
}
