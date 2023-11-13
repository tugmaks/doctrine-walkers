<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Maksim Tugaev
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/tugmaks/doctrine-walkers
 */

namespace Tugmaks\DoctrineWalkersTest\Ordering;

use Doctrine\ORM\Query;
use Tugmaks\DoctrineWalkers\Ordering\NULLS;
use Tugmaks\DoctrineWalkers\Ordering\NullsWalkerException;
use Tugmaks\DoctrineWalkers\Ordering\NullsWalkers;
use Tugmaks\DoctrineWalkersTest\AbstractWalkerTestCase;
use Tugmaks\DoctrineWalkersTest\DummyEntity;

final class NullsWalkerTest extends AbstractWalkerTestCase
{
    /**
     * @covers \Tugmaks\DoctrineWalkers\Ordering\NullsWalkers
     *
     * @dataProvider orderings
     *
     * @param array<string, NULLS> $hint
     */
    public function testNullWalker(string $dql, array $hint, string $sql): void
    {
        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalkers::class);
        $query->setHint(NullsWalkers::NULLS_RULE, $hint);

        self::assertSame($sql, $query->getSQL());
    }

    /**
     * @return iterable<string, array{0:string, 1: array<string, NULLS>, 2:string}>
     */
    public static function orderings(): iterable
    {
        yield 'Single field' => [
            \sprintf('SELECT d FROM %s d ORDER BY d.name DESC', DummyEntity::class),
            ['d.name' => NULLS::LAST],
            'SELECT d0_.id AS id_0, d0_.name AS name_1 FROM de_tbl d0_ ORDER BY d0_.name DESC NULLS LAST',
        ];

        yield 'Multiple fields' => [
            \sprintf('SELECT d FROM %s d ORDER BY d.name DESC, d.id DESC', DummyEntity::class),
            ['d.name' => NULLS::LAST, 'd.id' => NULLS::LAST],
            'SELECT d0_.id AS id_0, d0_.name AS name_1 FROM de_tbl d0_ ORDER BY d0_.name DESC NULLS LAST, d0_.id DESC NULLS LAST',
        ];

        yield 'Multiple fields but not all fields use LAST/FIRST' => [
            \sprintf('SELECT d FROM %s d ORDER BY d.name DESC, d.id DESC', DummyEntity::class),
            ['d.name' => NULLS::LAST],
            'SELECT d0_.id AS id_0, d0_.name AS name_1 FROM de_tbl d0_ ORDER BY d0_.name DESC NULLS LAST, d0_.id DESC',
        ];
    }

    /**
     * @covers \Tugmaks\DoctrineWalkers\Ordering\NullsWalkers::walkOrderByItem
     */
    public function testItThrowExceptionIfHintIsInvalid(): void
    {
        self::expectException(NullsWalkerException::class);
        self::expectExceptionMessage('Hint for NULLS FIRST/LAST should be provided as NULLS enum case...');

        $dql = \sprintf('SELECT d FROM %s d ORDER BY d.name DESC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalkers::class);
        $query->setHint(NullsWalkers::NULLS_RULE, ['d.name' => 'NULLS enum case expected here, but string provided']);

        $query->getSQL();
    }
}
