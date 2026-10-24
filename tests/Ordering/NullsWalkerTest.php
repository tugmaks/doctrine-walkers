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

namespace Tugmaks\DoctrineWalkersTest\Ordering;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\AST\OrderByItem;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\ParserResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tugmaks\DoctrineWalkers\Ordering\NULLS;
use Tugmaks\DoctrineWalkers\Ordering\NullsWalker;
use Tugmaks\DoctrineWalkers\Ordering\NullsWalkerException;
use Tugmaks\DoctrineWalkersTest\AbstractWalkerTestCase;
use Tugmaks\DoctrineWalkersTest\DummyEntity;

#[CoversClass(NullsWalker::class)]
final class NullsWalkerTest extends AbstractWalkerTestCase
{
    /**
     * @param array<string, NULLS> $hint
     */
    #[DataProvider('orderings')]
    public function testNullWalker(string $dql, array $hint, string $sql): void
    {
        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
        $query->setHint(NullsWalker::NULLS_RULE, $hint);

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
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_ ORDER BY d0_.name DESC NULLS LAST',
        ];

        yield 'Multiple fields' => [
            \sprintf('SELECT d FROM %s d ORDER BY d.name DESC, d.id DESC', DummyEntity::class),
            ['d.name' => NULLS::LAST, 'd.id' => NULLS::LAST],
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_ ORDER BY d0_.name DESC NULLS LAST, d0_.id DESC NULLS LAST',
        ];

        yield 'Multiple fields but not all fields use LAST/FIRST' => [
            \sprintf('SELECT d FROM %s d ORDER BY d.name DESC, d.id DESC', DummyEntity::class),
            ['d.name' => NULLS::LAST],
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_ ORDER BY d0_.name DESC NULLS LAST, d0_.id DESC',
        ];
    }

    public function testWithQueryBuilder(): void
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(DummyEntity::class, 'd')
            ->orderBy('d.name', 'DESC')
            ->getQuery();

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
        $query->setHint(NullsWalker::NULLS_RULE, ['d.name' => NULLS::LAST]);

        self::assertSame(
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_ ORDER BY d0_.name DESC NULLS LAST',
            $query->getSQL(),
        );
    }

    public function testItUsesDefaultOrderingWhenHintIsNotSet(): void
    {
        $dql = \sprintf('SELECT d FROM %s d ORDER BY d.name DESC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);

        self::assertSame(
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_ ORDER BY d0_.name DESC',
            $query->getSQL(),
        );
    }

    /**
     * @see https://github.com/tugmaks/doctrine-walkers/issues/19
     */
    public function testItThrowsExceptionIfHintFieldIsNotInOrderBy(): void
    {
        self::expectException(NullsWalkerException::class);
        self::expectExceptionMessage('NULLS FIRST/LAST hint refers to fields that are not used in ORDER BY: foo, d.iq.');

        $dql = \sprintf('SELECT d FROM %s d ORDER BY d.name DESC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
        $query->setHint(NullsWalker::NULLS_RULE, ['foo' => NULLS::FIRST, 'd.name' => NULLS::LAST, 'd.iq' => NULLS::LAST]);

        $query->getSQL();
    }

    public function testItKeepsDefaultOrderingForNonFieldOrderByItems(): void
    {
        $dql = \sprintf('SELECT d, LENGTH(d.name) AS HIDDEN len FROM %s d ORDER BY len DESC, d.name ASC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
        $query->setHint(NullsWalker::NULLS_RULE, ['d.name' => NULLS::FIRST]);

        self::assertSame(
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2, LENGTH(d0_.name) AS sclr_3 FROM de_tbl d0_ ORDER BY sclr_3 DESC, d0_.name ASC NULLS FIRST',
            $query->getSQL(),
        );
    }

    public function testItAppliesHintToHiddenAlias(): void
    {
        $dql = \sprintf('SELECT d, LENGTH(d.name) AS HIDDEN len FROM %s d ORDER BY len DESC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
        $query->setHint(NullsWalker::NULLS_RULE, ['len' => NULLS::LAST]);

        self::assertSame(
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2, LENGTH(d0_.name) AS sclr_3 FROM de_tbl d0_ ORDER BY sclr_3 DESC NULLS LAST',
            $query->getSQL(),
        );
    }

    public function testItAppliesHintToFieldsAndAliasesTogether(): void
    {
        $dql = \sprintf('SELECT d, LENGTH(d.name) AS HIDDEN len FROM %s d ORDER BY len DESC, d.name ASC, d.iq ASC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
        $query->setHint(NullsWalker::NULLS_RULE, ['len' => NULLS::LAST, 'd.name' => NULLS::FIRST]);

        self::assertSame(
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2, LENGTH(d0_.name) AS sclr_3 FROM de_tbl d0_ ORDER BY sclr_3 DESC NULLS LAST, d0_.name ASC NULLS FIRST, d0_.iq ASC',
            $query->getSQL(),
        );
    }

    public function testItThrowsExceptionIfAliasIsExpandedIntoMultipleColumns(): void
    {
        self::expectException(NullsWalkerException::class);
        self::expectExceptionMessage('NULLS FIRST/LAST cannot be applied to "ent" because it is expanded into multiple columns.');

        $dql = \sprintf('SELECT d AS ent FROM %s d ORDER BY ent DESC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
        $query->setHint(NullsWalker::NULLS_RULE, ['ent' => NULLS::LAST]);

        $query->getSQL();
    }

    public function testItThrowsExceptionIfHintRefersToExpressionInOrderBy(): void
    {
        self::expectException(NullsWalkerException::class);
        self::expectExceptionMessage('NULLS FIRST/LAST hint refers to fields that are not used in ORDER BY: d.name.');

        $dql = \sprintf('SELECT d FROM %s d ORDER BY LENGTH(d.name) DESC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
        $query->setHint(NullsWalker::NULLS_RULE, ['d.name' => NULLS::LAST]);

        $query->getSQL();
    }

    /**
     * @see https://github.com/tugmaks/doctrine-walkers/issues/18
     */
    public function testItAppliesHintToSubselectAlias(): void
    {
        $dql = \sprintf(
            'SELECT d, (SELECT MAX(d2.iq) FROM %1$s d2 WHERE d2.name = d.name) AS foo FROM %1$s d ORDER BY foo DESC',
            DummyEntity::class,
        );

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
        $query->setHint(NullsWalker::NULLS_RULE, ['foo' => NULLS::FIRST]);

        self::assertSame(
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2, (SELECT MAX(d1_.iq) AS sclr_4 FROM de_tbl d1_ WHERE d1_.name = d0_.name) AS sclr_3 FROM de_tbl d0_ ORDER BY sclr_3 DESC NULLS FIRST',
            $query->getSQL(),
        );
    }

    /**
     * @see https://github.com/tugmaks/doctrine-walkers/issues/18
     */
    public function testItAppliesHintToSubselectAliasWithQueryBuilder(): void
    {
        $subQuery = $this->entityManager->createQueryBuilder()
            ->select('MAX(d2.iq)')
            ->from(DummyEntity::class, 'd2')
            ->where('d2.name = d.name')
            ->getDQL();

        $query = $this->entityManager->createQueryBuilder()
            ->select('d')
            ->addSelect(\sprintf('(%s) AS foo', $subQuery))
            ->from(DummyEntity::class, 'd')
            ->orderBy('foo', 'DESC')
            ->getQuery();

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
        $query->setHint(NullsWalker::NULLS_RULE, ['foo' => NULLS::FIRST]);

        self::assertSame(
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2, (SELECT MAX(d1_.iq) AS sclr_4 FROM de_tbl d1_ WHERE d1_.name = d0_.name) AS sclr_3 FROM de_tbl d0_ ORDER BY sclr_3 DESC NULLS FIRST',
            $query->getSQL(),
        );
    }

    public function testItIgnoresHintWhenQueryHasNoOrderBy(): void
    {
        $dql = \sprintf('SELECT d FROM %s d', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
        $query->setHint(NullsWalker::NULLS_RULE, ['d.name' => NULLS::LAST]);

        self::assertSame(
            'SELECT d0_.id AS id_0, d0_.name AS name_1, d0_.iq AS iq_2 FROM de_tbl d0_',
            $query->getSQL(),
        );
    }

    public function testItThrowExceptionIfHintIsInvalid(): void
    {
        self::expectException(NullsWalkerException::class);
        self::expectExceptionMessage('Hint for NULLS FIRST/LAST should be provided as NULLS enum case...');

        $dql = \sprintf('SELECT d FROM %s d ORDER BY d.name DESC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
        $query->setHint(NullsWalker::NULLS_RULE, ['d.name' => 'NULLS enum case expected here, but string provided']);

        $query->getSQL();
    }

    public function testItThrowsAnErrorIfHintIsNotArray(): void
    {
        self::expectException(NullsWalkerException::class);
        self::expectExceptionMessage('Hint for NullsWalker should be an array...');

        $dql = \sprintf('SELECT d FROM %s d ORDER BY d.name DESC', DummyEntity::class);

        $query = $this->entityManager->createQuery($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
        $query->setHint(NullsWalker::NULLS_RULE, 'foo');

        $query->getSQL();
    }

    public function testItThrowsExceptionIfFieldIsNull(): void
    {
        self::expectException(NullsWalkerException::class);
        self::expectExceptionMessage('PathExpression field cannot be null.');

        $query = new Query($this->entityManager);
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
        $query->setHint(NullsWalker::NULLS_RULE, []);

        $walker = new NullsWalker($query, new ParserResult(), []);

        $pathExpression = new PathExpression(PathExpression::TYPE_STATE_FIELD, 'd', field: null);
        $pathExpression->type = PathExpression::TYPE_STATE_FIELD;
        $orderByItem = new OrderByItem($pathExpression);
        $orderByItem->type = 'ASC';

        $walker->walkOrderByItem($orderByItem);
    }
}
