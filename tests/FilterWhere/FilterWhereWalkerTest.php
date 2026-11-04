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

namespace Tugmaks\DoctrineWalkersTest\FilterWhere;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tugmaks\DoctrineWalkers\FilterWhere\FilterWhereWalker;
use Tugmaks\DoctrineWalkers\FilterWhere\FilterWhereWalkerException;
use Tugmaks\DoctrineWalkersTest\AbstractWalkerTestCase;
use Tugmaks\DoctrineWalkersTest\DummyEntity;
use Tugmaks\DoctrineWalkersTest\DummyLinkedEntity;

#[CoversClass(FilterWhereWalker::class)]
final class FilterWhereWalkerTest extends AbstractWalkerTestCase
{
    #[DataProvider('provideConditions')]
    public function testTranslatesCondition(mixed $condition, string $expectedSql): void
    {
        $query = $this->createFilteredQuery(['matching' => $condition]);

        self::assertSame(
            'SELECT COUNT(d0_.id) FILTER (WHERE ' . $expectedSql . ') AS sclr_0 FROM de_tbl d0_',
            $query->getSQL(),
        );
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideConditions(): iterable
    {
        $expr = new Expr();

        yield 'comparison with parameter' => [
            $expr->gt('d.iq', ':iq_min'),
            'd0_.iq > ?',
        ];

        yield 'positional parameter' => [
            $expr->gt('d.iq', '?1'),
            'd0_.iq > ?',
        ];

        yield 'comparison with numeric literal' => [
            $expr->gte('d.iq', 100),
            'd0_.iq >= 100',
        ];

        yield 'comparison of two fields' => [
            $expr->neq('d.iq', 'd.id'),
            'd0_.iq <> d0_.id',
        ];

        yield 'like with string literal' => [
            $expr->like('d.name', $expr->literal("O'Neil%")),
            "d0_.name LIKE 'O''Neil%'",
        ];

        yield 'not like' => [
            $expr->notLike('d.name', ':pattern'),
            'd0_.name NOT LIKE ?',
        ];

        yield 'and' => [
            $expr->andX($expr->gte('d.iq', ':low'), $expr->lt('d.iq', ':high')),
            'd0_.iq >= ? AND d0_.iq < ?',
        ];

        yield 'nested and / or' => [
            $expr->andX(
                $expr->gte('d.iq', ':low'),
                $expr->orX($expr->eq('d.name', ':name'), $expr->isNull('d.name')),
            ),
            'd0_.iq >= ? AND (d0_.name = ? OR d0_.name IS NULL)',
        ];

        yield 'single part composite has no parentheses' => [
            $expr->andX($expr->orX($expr->eq('d.name', ':name'))),
            'd0_.name = ?',
        ];

        yield 'is not null' => [
            $expr->isNotNull('d.name'),
            'd0_.name IS NOT NULL',
        ];

        yield 'between' => [
            $expr->between('d.iq', ':low', ':high'),
            'd0_.iq BETWEEN ? AND ?',
        ];

        yield 'in with parameter' => [
            $expr->in('d.iq', ':values'),
            'd0_.iq IN (?)',
        ];

        yield 'in with literals' => [
            $expr->in('d.name', ['a', 'b']),
            "d0_.name IN ('a', 'b')",
        ];

        yield 'not in' => [
            $expr->notIn('d.iq', [1, 2]),
            'd0_.iq NOT IN (1, 2)',
        ];

        yield 'not' => [
            $expr->not($expr->orX($expr->eq('d.iq', 1), $expr->eq('d.iq', 2))),
            'NOT (d0_.iq = 1 OR d0_.iq = 2)',
        ];
    }

    public function testAppliesDifferentFiltersToMultipleAggregateColumns(): void
    {
        $expr = new Expr();

        $query = $this->entityManager->createQuery(\sprintf(
            'SELECT COUNT(d.id) AS low_iq, SUM(d.iq) AS total_iq, AVG(d.iq) AS high_iq FROM %s d',
            DummyEntity::class,
        ));

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);
        $query->setHint(FilterWhereWalker::FILTER_WHERE, [
            'low_iq' => $expr->lt('d.iq', ':low_max'),
            'high_iq' => $expr->gte('d.iq', ':high_min'),
        ]);

        self::assertSame(
            'SELECT COUNT(d0_.id) FILTER (WHERE d0_.iq < ?) AS sclr_0, '
            . 'SUM(d0_.iq) AS sclr_1, '
            . 'AVG(d0_.iq) FILTER (WHERE d0_.iq >= ?) AS sclr_2 '
            . 'FROM de_tbl d0_',
            $query->getSQL(),
        );
    }

    public function testWorksWithQueryBuilder(): void
    {
        $qb = $this->entityManager->createQueryBuilder();
        $highIq = $qb->expr()->gte('d.iq', ':iq_min');

        $query = $qb
            ->select('COUNT(d.id) AS high_iq_count', 'COUNT(d.id) AS total')
            ->from(DummyEntity::class, 'd')
            ->where($qb->expr()->isNotNull('d.name'))
            ->setParameter('iq_min', 100)
            ->getQuery();

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);
        $query->setHint(FilterWhereWalker::FILTER_WHERE, ['high_iq_count' => $highIq]);

        self::assertSame(
            'SELECT COUNT(d0_.id) FILTER (WHERE d0_.iq >= ?) AS sclr_0, COUNT(d0_.id) AS sclr_1 '
            . 'FROM de_tbl d0_ WHERE d0_.name IS NOT NULL',
            $query->getSQL(),
        );
    }

    public function testDoesNothingWhenHintIsMissing(): void
    {
        $query = $this->entityManager->createQuery(\sprintf(
            'SELECT COUNT(d.id) AS high_iq_count FROM %s d',
            DummyEntity::class,
        ));

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);

        self::assertSame('SELECT COUNT(d0_.id) AS sclr_0 FROM de_tbl d0_', $query->getSQL());
    }

    public function testThrowsWhenHintIsNotAnArray(): void
    {
        $query = $this->createFilteredQuery((new Expr())->gt('d.iq', 1));

        $this->expectException(FilterWhereWalkerException::class);
        $this->expectExceptionMessage('Hint for FilterWhereWalker should be an array of "alias => condition" pairs.');

        $query->getSQL();
    }

    public function testThrowsWhenHintRefersToUnknownSelectAlias(): void
    {
        $query = $this->createFilteredQuery(['unknown' => (new Expr())->gt('d.iq', 1)]);

        $this->expectException(FilterWhereWalkerException::class);
        $this->expectExceptionMessage('FILTER WHERE hint refers to alias "unknown" that is not present in the SELECT clause.');

        $query->getSQL();
    }

    public function testThrowsWhenHintRefersToNonAggregateAlias(): void
    {
        $query = $this->entityManager->createQuery(\sprintf('SELECT d.iq AS iq FROM %s d', DummyEntity::class));

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);
        $query->setHint(FilterWhereWalker::FILTER_WHERE, ['iq' => (new Expr())->gt('d.iq', 1)]);

        $this->expectException(FilterWhereWalkerException::class);
        $this->expectExceptionMessage('FILTER WHERE hint refers to alias "iq" that is not an aggregate expression.');

        $query->getSQL();
    }

    #[DataProvider('provideUnsupportedConditions')]
    public function testThrowsOnUnsupportedCondition(mixed $condition, string $expectedMessage): void
    {
        $query = $this->createFilteredQuery(['matching' => $condition]);

        $this->expectException(FilterWhereWalkerException::class);
        $this->expectExceptionMessage($expectedMessage);

        $query->getSQL();
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideUnsupportedConditions(): iterable
    {
        $expr = new Expr();

        yield 'raw DQL string' => [
            'd.iq > 100',
            'FILTER WHERE condition "d.iq > 100" is not supported, build it with Doctrine\ORM\Query\Expr.',
        ];

        yield 'unknown DQL alias' => [
            $expr->gt('x.iq', 1),
            'FILTER WHERE condition refers to unknown alias "x".',
        ];

        yield 'unknown field' => [
            $expr->gt('d.iqq', 1),
            'FILTER WHERE condition refers to unknown field "d.iqq".',
        ];

        yield 'unsupported operator' => [
            $expr->isMemberOf(':item', 'd.name'),
            'FILTER WHERE comparison operator "MEMBER OF" is not supported.',
        ];

        yield 'unsupported function' => [
            $expr->eq($expr->lower('d.name'), ':name'),
            'FILTER WHERE operand of type "Doctrine\ORM\Query\Expr\Func" is not supported.',
        ];

        yield 'unsupported function as condition' => [
            $expr->lower('d.name'),
            'FILTER WHERE function "LOWER" is not supported.',
        ];

        yield 'in with empty list' => [
            $expr->in('d.iq', []),
            'FILTER WHERE function "d.iq IN" is not supported.',
        ];

        yield 'unsupported operand' => [
            $expr->eq('d.name', 'name'),
            'FILTER WHERE operand "name" is not supported.',
        ];

        yield 'badly quoted string literal' => [
            $expr->eq('d.name', "'a' OR 'b'"),
            "FILTER WHERE operand \"'a' OR 'b'\" is not supported.",
        ];

        yield 'empty composite' => [
            $expr->andX(),
            'FILTER WHERE condition should not be an empty andX() / orX().',
        ];

        yield 'unsupported type' => [
            42,
            'FILTER WHERE condition of type "int" is not supported.',
        ];
    }

    public function testAppliesFilterToCountDistinct(): void
    {
        $query = $this->entityManager->createQuery(\sprintf(
            'SELECT COUNT(DISTINCT d.name) AS names FROM %s d',
            DummyEntity::class,
        ));

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);
        $query->setHint(FilterWhereWalker::FILTER_WHERE, ['names' => (new Expr())->gt('d.iq', 100)]);

        self::assertSame(
            'SELECT COUNT(DISTINCT d0_.name) FILTER (WHERE d0_.iq > 100) AS sclr_0 FROM de_tbl d0_',
            $query->getSQL(),
        );
    }

    public function testAppliesFilterToAggregateInsideCoalesce(): void
    {
        $query = $this->entityManager->createQuery(\sprintf(
            'SELECT COALESCE(SUM(d.iq), 0) AS total_iq FROM %s d',
            DummyEntity::class,
        ));

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);
        $query->setHint(FilterWhereWalker::FILTER_WHERE, [
            'total_iq' => (new Expr())->gt('d.iq', 100),
        ]);

        self::assertSame(
            'SELECT COALESCE(SUM(d0_.iq) FILTER (WHERE d0_.iq > 100), 0) AS sclr_0 FROM de_tbl d0_',
            $query->getSQL(),
        );
    }

    public function testAppliesFilterOnlyToCoalesceFromTheHint(): void
    {
        $query = $this->entityManager->createQuery(\sprintf(
            'SELECT COALESCE(MAX(d.iq), 0) AS max_iq, COALESCE(SUM(d.iq), :default) AS total_iq, COALESCE(MIN(d.iq), 0) AS min_iq FROM %s d',
            DummyEntity::class,
        ));

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);
        $query->setHint(FilterWhereWalker::FILTER_WHERE, [
            'total_iq' => (new Expr())->gt('d.iq', ':iq_min'),
        ]);

        self::assertSame(
            'SELECT COALESCE(MAX(d0_.iq), 0) AS sclr_0, '
            . 'COALESCE(SUM(d0_.iq) FILTER (WHERE d0_.iq > ?), ?) AS sclr_1, '
            . 'COALESCE(MIN(d0_.iq), 0) AS sclr_2 '
            . 'FROM de_tbl d0_',
            $query->getSQL(),
        );
    }

    public function testAppliesFilterOnceToNestedCoalesce(): void
    {
        $query = $this->entityManager->createQuery(\sprintf(
            'SELECT COALESCE(SUM(d.iq), COALESCE(MAX(d.iq), 0)) AS total_iq FROM %s d',
            DummyEntity::class,
        ));

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);
        $query->setHint(FilterWhereWalker::FILTER_WHERE, [
            'total_iq' => (new Expr())->gt('d.iq', 100),
        ]);

        self::assertSame(
            'SELECT COALESCE(SUM(d0_.iq) FILTER (WHERE d0_.iq > 100), COALESCE(MAX(d0_.iq), 0)) AS sclr_0 FROM de_tbl d0_',
            $query->getSQL(),
        );
    }

    #[DataProvider('provideUnsupportedCoalesce')]
    public function testThrowsOnUnsupportedCoalesce(string $selectExpression, string $expectedMessage): void
    {
        $query = $this->entityManager->createQuery(\sprintf(
            'SELECT %s AS value FROM %s d',
            $selectExpression,
            DummyEntity::class,
        ));

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);
        $query->setHint(FilterWhereWalker::FILTER_WHERE, ['value' => (new Expr())->gt('d.iq', 100)]);

        $this->expectException(FilterWhereWalkerException::class);
        $this->expectExceptionMessage($expectedMessage);

        $query->getSQL();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideUnsupportedCoalesce(): iterable
    {
        yield 'no aggregate' => [
            'COALESCE(d.iq, 0)',
            'FILTER WHERE hint refers to alias "value": COALESCE should have an aggregate as a direct argument, none found.',
        ];

        yield 'aggregate inside arithmetic' => [
            'COALESCE(SUM(d.iq) + 1, 0)',
            'FILTER WHERE hint refers to alias "value": COALESCE should have an aggregate as a direct argument, none found.',
        ];

        yield 'several aggregates' => [
            'COALESCE(MAX(d.iq), MIN(d.iq), 0)',
            'FILTER WHERE hint refers to alias "value": COALESCE should have exactly one aggregate as a direct argument, 2 found.',
        ];
    }

    #[DataProvider('provideAssociationConditions')]
    public function testTranslatesToOneAssociationToJoinColumn(mixed $condition, string $expectedSql): void
    {
        $query = $this->createLinkedQuery($condition);

        self::assertSame(
            'SELECT COUNT(d0_.id) FILTER (WHERE ' . $expectedSql . ') AS sclr_0 FROM de_linked_tbl d0_',
            $query->getSQL(),
        );
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideAssociationConditions(): iterable
    {
        $expr = new Expr();

        yield 'association compared with parameter' => [
            $expr->eq('d.related', ':related'),
            'd0_.related_id = ?',
        ];

        yield 'association is not null' => [
            $expr->isNotNull('d.related'),
            'd0_.related_id IS NOT NULL',
        ];

        yield 'association in list' => [
            $expr->in('d.related', ':related_ids'),
            'd0_.related_id IN (?)',
        ];
    }

    #[DataProvider('provideUnsupportedAssociations')]
    public function testThrowsOnUnsupportedAssociation(string $association): void
    {
        $query = $this->createLinkedQuery((new Expr())->isNotNull('d.' . $association));

        $this->expectException(FilterWhereWalkerException::class);
        $this->expectExceptionMessage(\sprintf(
            'FILTER WHERE condition refers to association "d.%s" that is not supported: '
            . 'only to-one owning side associations with a single join column can be used.',
            $association,
        ));

        $query->getSQL();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnsupportedAssociations(): iterable
    {
        yield 'to-many' => ['relatedMany'];

        yield 'composite join columns' => ['composite'];
    }

    private function createFilteredQuery(mixed $hint): Query
    {
        $query = $this->entityManager->createQuery(\sprintf(
            'SELECT COUNT(d.id) AS matching FROM %s d',
            DummyEntity::class,
        ));

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);
        $query->setHint(FilterWhereWalker::FILTER_WHERE, $hint);

        return $query;
    }

    private function createLinkedQuery(mixed $condition): Query
    {
        $query = $this->entityManager->createQuery(\sprintf(
            'SELECT COUNT(d.id) AS matching FROM %s d',
            DummyLinkedEntity::class,
        ));

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);
        $query->setHint(FilterWhereWalker::FILTER_WHERE, ['matching' => $condition]);

        return $query;
    }
}
