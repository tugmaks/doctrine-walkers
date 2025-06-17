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

namespace Tugmaks\DoctrineWalkersTest\WithTies;

use Doctrine\ORM\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tugmaks\DoctrineWalkers\WithTies\WithTiesWalker;
use Tugmaks\DoctrineWalkersTest\AbstractWalkerTestCase;
use Tugmaks\DoctrineWalkersTest\DummyEntity;

#[CoversClass(WithTiesWalker::class)]
final class WithTiesWalkerTest extends AbstractWalkerTestCase
{
    #[DataProvider('withTies')]
    public function testWithTiesWalker(?int $limit, int $offset, string $sql): void
    {
        $query = $this->entityManager
            ->createQuery(\sprintf('SELECT d FROM %s d ORDER BY d.iq DESC', DummyEntity::class))
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, WithTiesWalker::class);

        self::assertSame($sql, $query->getSQL());
    }

    /**
     * @return iterable<array{0:null|int, 1:int, 2:string}>
     */
    public static function withTies(): iterable
    {
        yield 'Only limit' => [
            5,
            0,
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_ ORDER BY d0_.iq DESC FETCH NEXT 5 ROWS WITH TIES',
        ];

        yield 'Limit and offset' => [
            5,
            25,
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_ ORDER BY d0_.iq DESC OFFSET 25 FETCH NEXT 5 ROWS WITH TIES',
        ];

        yield 'Only offset (No modification)' => [
            null,
            25,
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_ ORDER BY d0_.iq DESC OFFSET 25',
        ];

        yield 'No limit and no offset (No modification)' => [
            null,
            0,
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_ ORDER BY d0_.iq DESC',
        ];
    }
}
