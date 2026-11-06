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

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tugmaks\DoctrineWalkers\Returning\ReturningClause;
use Tugmaks\DoctrineWalkers\Returning\ReturningExecutor;
use Tugmaks\DoctrineWalkers\Returning\ReturningWalker;
use Tugmaks\DoctrineWalkersTest\Integration\Entity\Player;
use Tugmaks\DoctrineWalkersTest\Integration\Entity\Team;

#[CoversClass(ReturningClause::class)]
#[CoversClass(ReturningExecutor::class)]
#[CoversClass(ReturningWalker::class)]
final class ReturningWalkerIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->persist(new Player('Ivan', 'RU', 50), new Player('Oleg', 'RU', 60), new Player('Hans', 'DE', 80));
    }

    public function testUpdateWithParametersReturnsChangedRows(): void
    {
        $query = $this->createReturningQuery(
            'UPDATE %s p SET p.score = :score WHERE p.country = :country',
            new ReturningClause(['name', 'score']),
        );
        $query->setParameter('score', 100);
        $query->setParameter('country', 'RU');

        self::assertEqualsCanonicalizing(
            [['name' => 'Ivan', 'score' => 100], ['name' => 'Oleg', 'score' => 100]],
            $query->getArrayResult(),
        );
    }

    public function testUpdateWithLiteralsReturnsChangedRow(): void
    {
        $query = $this->createReturningQuery(
            "UPDATE %s p SET p.score = 100 WHERE p.name = 'Hans'",
            new ReturningClause(['name', 'score']),
        );

        self::assertSame([['name' => 'Hans', 'score' => 100]], $query->getArrayResult());
    }

    /**
     * @param \Closure(Query): mixed $fetch
     */
    #[DataProvider('provideResultMethods')]
    public function testResultMethodsReturnRows(string $where, \Closure $fetch, mixed $expected): void
    {
        $query = $this->createReturningQuery(
            'UPDATE %s p SET p.score = 100 WHERE ' . $where,
            new ReturningClause(['name']),
        );

        self::assertSame($expected, $fetch($query));
    }

    /**
     * @return iterable<string, array{string, \Closure(Query): mixed, mixed}>
     */
    public static function provideResultMethods(): iterable
    {
        $twoRows = "p.country = 'RU'";
        $oneRow = "p.name = 'Ivan'";
        $noRows = "p.name = 'Nobody'";
        $rows = [['name' => 'Ivan'], ['name' => 'Oleg']];

        yield 'execute()' => [$twoRows, static fn (Query $query): mixed => $query->execute(), $rows];

        yield 'getResult()' => [$twoRows, static fn (Query $query): mixed => $query->getResult(), $rows];

        yield 'getArrayResult()' => [$twoRows, static fn (Query $query): array => $query->getArrayResult(), $rows];

        yield 'getScalarResult()' => [$twoRows, static fn (Query $query): array => $query->getScalarResult(), $rows];

        yield 'toIterable()' => [$twoRows, static fn (Query $query): array => \iterator_to_array($query->toIterable(), false), $rows];

        yield 'getSingleColumnResult()' => [$twoRows, static fn (Query $query): array => $query->getSingleColumnResult(), ['Ivan', 'Oleg']];

        yield 'getSingleResult()' => [$oneRow, static fn (Query $query): mixed => $query->getSingleResult(), ['name' => 'Ivan']];

        yield 'getOneOrNullResult()' => [$oneRow, static fn (Query $query): mixed => $query->getOneOrNullResult(), ['name' => 'Ivan']];

        yield 'getOneOrNullResult() without rows' => [$noRows, static fn (Query $query): mixed => $query->getOneOrNullResult(), null];

        yield 'getSingleScalarResult()' => [$oneRow, static fn (Query $query): mixed => $query->getSingleScalarResult(), 'Ivan'];
    }

    public function testRowsAreModifiedEvenWhenResultMethodThrows(): void
    {
        $query = $this->createReturningQuery("UPDATE %s p SET p.score = 100 WHERE p.country = 'RU'", new ReturningClause(['name']));

        try {
            $query->getSingleResult();
            self::fail('Expected getSingleResult() to fail on two returned rows.');
        } catch (NonUniqueResultException) {
        }

        self::assertSame([100, 100], $this->fetchScoresOfCountry('RU'));
    }

    public function testTransactionRollsBackModificationWhenResultMethodThrows(): void
    {
        $query = $this->createReturningQuery("UPDATE %s p SET p.score = 100 WHERE p.country = 'RU'", new ReturningClause(['name']));

        try {
            $this->entityManager->wrapInTransaction(static fn (): mixed => $query->getSingleResult());
            self::fail('Expected getSingleResult() to fail on two returned rows.');
        } catch (NonUniqueResultException) {
        }

        self::assertSame([50, 60], $this->fetchScoresOfCountry('RU'));
    }

    public function testDeleteReturnsAllColumnsOfDeletedRow(): void
    {
        $query = $this->createReturningQuery('DELETE FROM %s p WHERE p.name = :name', new ReturningClause());
        $query->setParameter('name', 'Hans');

        self::assertSame(
            [['id' => 3, 'name' => 'Hans', 'country' => 'DE', 'score' => 80, 'team_id' => null]],
            $query->getArrayResult(),
        );
        self::assertSame(2, $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM it_players'));
    }

    public function testExpressionWithAliasIsReturnedUnderAlias(): void
    {
        $query = $this->createReturningQuery(
            "UPDATE %s p SET p.score = 100 WHERE p.name = 'Hans'",
            new ReturningClause(['name', 'score * 2 AS doubled']),
        );

        self::assertSame([['name' => 'Hans', 'doubled' => 200]], $query->getArrayResult());
    }

    public function testColumnListAsStringIsSplitIntoColumns(): void
    {
        $query = $this->createReturningQuery(
            "UPDATE %s p SET p.score = NULL WHERE p.name = 'Hans'",
            new ReturningClause("name, COALESCE(score, 0) AS score, CONCAT(name, ', ', country) AS label"),
        );

        self::assertSame([['name' => 'Hans', 'score' => 0, 'label' => 'Hans, DE']], $query->getArrayResult());
    }

    public function testUnmappedColumnIsReturnedWithoutConversion(): void
    {
        $query = $this->createReturningQuery(
            "UPDATE %s p SET p.score = 100 WHERE p.name = 'Hans'",
            new ReturningClause(['name', 'ctid']),
        );

        $rows = $query->getArrayResult();

        self::assertCount(1, $rows);
        self::assertIsArray($rows[0]);
        self::assertSame('Hans', $rows[0]['name']);
        self::assertIsString($rows[0]['ctid']);
        self::assertMatchesRegularExpression('/^\(\d+,\d+\)$/', $rows[0]['ctid']);
    }

    public function testAllColumnsSkipInverseSideAssociations(): void
    {
        $this->persist(new Team('Red'));

        $query = $this->entityManager->createQuery(\sprintf("DELETE FROM %s t WHERE t.name = 'Red'", Team::class));
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);
        $query->setHint(ReturningWalker::RETURNING_CLAUSE, new ReturningClause());

        self::assertSame([['id' => 1, 'name' => 'Red']], $query->getArrayResult());
    }

    public function testRowsAreReturnedWhenParserResultComesFromQueryCache(): void
    {
        $cache = new SerializingCachePool();

        foreach (['Ivan' => 101, 'Oleg' => 102] as $name => $score) {
            $query = $this->createReturningQuery(
                'UPDATE %s p SET p.score = :score WHERE p.name = :name',
                new ReturningClause(['name', 'score']),
            );
            $query->setQueryCache($cache);
            $query->setParameter('score', $score);
            $query->setParameter('name', $name);

            self::assertSame([['name' => $name, 'score' => $score]], $query->getArrayResult());
        }

        self::assertCount(1, $cache, 'The second query must reuse the cached parser result.');
    }

    public function testNoMatchingRowsReturnsEmptyList(): void
    {
        $query = $this->createReturningQuery(
            "DELETE FROM %s p WHERE p.name = 'Nobody'",
            new ReturningClause(['name']),
        );

        self::assertSame([], $query->getArrayResult());
    }

    /**
     * @return list<mixed>
     */
    private function fetchScoresOfCountry(string $country): array
    {
        return $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT score FROM it_players WHERE country = ? ORDER BY id',
            [$country],
        );
    }

    private function createReturningQuery(string $dql, ReturningClause $returningClause): Query
    {
        $query = $this->entityManager->createQuery(\sprintf($dql, Player::class));
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);
        $query->setHint(ReturningWalker::RETURNING_CLAUSE, $returningClause);

        return $query;
    }
}
