[![codecov](https://codecov.io/gh/tugmaks/doctrine-walkers/graph/badge.svg?token=4YXA0059QT)](https://codecov.io/gh/tugmaks/doctrine-walkers)
# Doctrine walkers

A collection of custom Doctrine ORM output walkers for commonly used SQL clauses. Each walker can be applied per-query via a query hint without modifying your entities or repository code.

## Installation

```bash
composer require tugmaks/doctrine-walkers
```

## Walkers

Listed in alphabetical order.

- [`DISTINCT ON`](#distinct-on)
- [`FILTER (WHERE ...)`](#filter-where-)
- [`FOR UPDATE`](#for-update)
- [`NULLS FIRST` / `NULLS LAST`](#nulls-first--nulls-last)
- [`RETURNING`](#returning)
- [`TABLESAMPLE`](#tablesample)
- [`WITH TIES`](#with-ties)

---

## `DISTINCT ON`

Adds a `DISTINCT ON (expr)` clause to the `SELECT` based on the hint — a PostgreSQL-only feature that keeps only one row per unique value of the specified expression.

### Example via DQL

```php
$query = $this->entityManager->createQuery('SELECT u FROM App\Entity\User u ORDER BY u.name DESC');

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, DistinctOnWalker::class);
$query->setHint(DistinctOnWalker::DISTINCT_ON, ['u.name']);

$query->getSQL();
```

### Example via QueryBuilder

```php
$query = $this->entityManager->createQueryBuilder()
    ->select('u')
    ->from(User::class, 'u')
    ->orderBy('u.name', 'DESC')
    ->getQuery();

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, DistinctOnWalker::class);
$query->setHint(DistinctOnWalker::DISTINCT_ON, ['u.name']);

$query->getSQL();
```

**Generated SQL:**
```sql
SELECT DISTINCT ON (u0_.name) u0_.id AS id_0, u0_.name AS name_1 FROM users u0_ ORDER BY u0_.name DESC
```

---

## `FILTER (WHERE ...)`

Adds a `FILTER (WHERE ...)` clause to an aggregate selected under a result alias. Useful when one query returns several aggregates with different conditions, or a filtered aggregate next to an unfiltered total. With a single aggregate, a plain `WHERE` does the same. Supported by PostgreSQL and SQLite.

The hint maps a `SELECT` alias to a condition built with Doctrine's `Expr` (`$qb->expr()` or `new Expr()`). Supported: `andX`, `orX`, `not`, `eq`, `neq`, `lt`, `lte`, `gt`, `gte`, `like`, `notLike`, `in`, `notIn`, `isNull`, `isNotNull`, `between` and `literal`. Operands are field paths (`u.iq`), to-one associations (`u.team`), parameters (`:iq_min`) or literals.

The aggregate can be `COUNT`, `SUM`, `AVG`, `MIN` or `MAX`, including `COUNT(DISTINCT ...)` and an aggregate wrapped in `COALESCE(...)`.

### Example

```php
$qb = $this->entityManager->createQueryBuilder();

$query = $qb
    ->select('COUNT(u.id) AS high_iq_count', 'COUNT(u.id) AS total')
    ->from(User::class, 'u')
    ->setParameter('iq_min', 100)
    ->getQuery();

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);
$query->setHint(FilterWhereWalker::FILTER_WHERE, [
    'high_iq_count' => $qb->expr()->andX(
        $qb->expr()->gte('u.iq', ':iq_min'),
        $qb->expr()->isNotNull('u.name'),
    ),
]);

$query->getSQL();
```

**Generated SQL:**
```sql
SELECT COUNT(u0_.id) FILTER (WHERE u0_.iq >= ? AND u0_.name IS NOT NULL) AS sclr_0, COUNT(u0_.id) AS sclr_1 FROM users u0_
```

Without the query builder, create the expression builder directly:

```php
$expr = new Expr();

$query = $this->entityManager->createQuery(
    'SELECT COUNT(u.id) AS high_iq_count, COUNT(u.id) AS total FROM App\Entity\User u',
);
$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);
$query->setHint(FilterWhereWalker::FILTER_WHERE, [
    'high_iq_count' => $expr->gte('u.iq', ':iq_min'),
]);
$query->setParameter('iq_min', 100);
```

### Associations

A to-one association on the owning side with a single join column is compared by its foreign key column. For example, members of one team next to all users (`User::$team` is a `ManyToOne`):

```php
$query = $this->entityManager->createQuery(
    'SELECT COUNT(u.id) AS total, COUNT(u.id) AS team_members FROM App\Entity\User u',
);
$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);
$query->setHint(FilterWhereWalker::FILTER_WHERE, [
    'team_members' => $expr->eq('u.team', ':team'),
]);
$query->setParameter('team', $team->getId());
```

```sql
SELECT COUNT(u0_.id) AS sclr_0, COUNT(u0_.id) FILTER (WHERE u0_.team_id = ?) AS sclr_1 FROM users u0_
```

Prefer binding the scalar identifier. An entity object also works: Doctrine replaces it with its identifier and infers the parameter type from that value. If the identifier has a custom DBAL type (for example `uuid`), pass the type explicitly: `setParameter('team', $team->getId(), 'uuid')`.

### `COALESCE` and `COUNT(DISTINCT ...)`

Balance, income and expenses in one query, plus the number of accounts that received money:

```php
$query = $this->entityManager->createQuery(
    'SELECT COALESCE(SUM(t.amount), 0) AS balance,'
    . ' COALESCE(SUM(t.amount), 0) AS income,'
    . ' COALESCE(SUM(t.amount), 0) AS expenses,'
    . ' COUNT(DISTINCT t.account) AS accounts_with_income'
    . ' FROM App\Entity\Transaction t',
);
$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, FilterWhereWalker::class);
$query->setHint(FilterWhereWalker::FILTER_WHERE, [
    'income' => $expr->gt('t.amount', 0),
    'expenses' => $expr->lt('t.amount', 0),
    'accounts_with_income' => $expr->gt('t.amount', 0),
]);
```

```sql
SELECT COALESCE(SUM(t0_.amount), 0) AS sclr_0,
       COALESCE(SUM(t0_.amount) FILTER (WHERE t0_.amount > 0), 0) AS sclr_1,
       COALESCE(SUM(t0_.amount) FILTER (WHERE t0_.amount < 0), 0) AS sclr_2,
       COUNT(DISTINCT t0_.account_id) FILTER (WHERE t0_.amount > 0) AS sclr_3
FROM transactions t0_
```

The filter is added to the aggregate inside `COALESCE`, so `COALESCE` must have exactly one aggregate as a direct argument.

### Limitations

- Only to-one owning side associations with a single join column; to-many, inverse side and composite foreign keys are rejected.
- No nested paths such as `u.team.id`: compare `u.team` instead.
- No arbitrary functions (`LOWER`, `CONCAT`, ...), `CASE` or arithmetic in conditions.
- `COALESCE` with several aggregates or with an aggregate inside arithmetic (`COALESCE(SUM(t.amount) + 1, 0)`) is rejected.
- `SELECT` only, `UPDATE` / `DELETE` are not supported.

---

## `FOR UPDATE`

Adds a `FOR UPDATE` clause with configurable lock strength and options (`SKIP LOCKED`, `NOWAIT`). Useful for pessimistic locking.

### Example

```php
$query = $this->entityManager->createQueryBuilder()
    ->select('u')
    ->from(User::class, 'u')
    ->where('u.id = 1')
    ->getQuery();

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, LockingWalker::class);
$query->setHint(LockingWalker::LOCKING_CLAUSE, new LockingClause(LockStrength::UPDATE, Option::SKIP_LOCKED));

$query->getSQL();
```

**Generated SQL:**
```sql
SELECT u0_.id AS id_0, u0_.name AS name_1 FROM users u0_ WHERE u0_.id = 1 FOR UPDATE SKIP LOCKED
```

---

## `NULLS FIRST` / `NULLS LAST`

Adds `NULLS FIRST` / `NULLS LAST` to `ORDER BY` clauses. Useful for controlling null sorting order in PostgreSQL.

Hints accept entity fields (`u.name`) as well as result variable aliases, including subselect and `HIDDEN` aliases.

### Example

```php
$query = $this->entityManager->createQueryBuilder()
    ->select('u')
    ->from(User::class, 'u')
    ->orderBy('u.name', 'DESC')
    ->getQuery();

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
$query->setHint(NullsWalker::NULLS_RULE, ['u.name' => NULLS::LAST]);

$query->getSQL();
```

**Generated SQL:**
```sql
SELECT u0_.id AS id_0, u0_.name AS name_1 FROM users u0_ ORDER BY u0_.name DESC NULLS LAST
```

### Example with an alias

```php
$subQuery = $this->entityManager->createQueryBuilder()
    ->select('MAX(u2.id)')
    ->from(User::class, 'u2')
    ->where('u2.name = u.name')
    ->getDQL();

$query = $this->entityManager->createQueryBuilder()
    ->select('u')
    ->addSelect(sprintf('(%s) AS max_id', $subQuery))
    ->from(User::class, 'u')
    ->orderBy('max_id', 'DESC')
    ->getQuery();

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
$query->setHint(NullsWalker::NULLS_RULE, ['max_id' => NULLS::FIRST]);

$query->getSQL();
```

**Generated SQL:**
```sql
SELECT u0_.id AS id_0, u0_.name AS name_1, (SELECT MAX(u1_.id) AS sclr_3 FROM users u1_ WHERE u1_.name = u0_.name) AS sclr_2 FROM users u0_ ORDER BY sclr_2 DESC NULLS FIRST
```

---

## `RETURNING`

Adds a `RETURNING` clause to DQL `UPDATE` and `DELETE` queries and returns the modified rows as the query result (PostgreSQL).

Accepts `'*'` (all mapped columns of the entity) or a list of columns, e.g. `['id', 'name']`. An expression needs an alias, which becomes the row key: `'score * 2 AS doubled'`.

Each row is an array keyed by column name. Values of mapped columns are converted with the column's Doctrine type, values of expressions are returned as the driver gives them. `getArrayResult()`, `getResult()` and `execute()` all return the rows instead of the affected row count.

### UPDATE

```php
$query = $this->entityManager->createQueryBuilder()
    ->update(User::class, 'u')
    ->set('u.name', ':name')
    ->where('u.id = :id')
    ->setParameter('name', 'new')
    ->setParameter('id', 1)
    ->getQuery();

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);
$query->setHint(ReturningWalker::RETURNING_CLAUSE, new ReturningClause(['id', 'name']));

$rows = $query->getArrayResult(); // [['id' => 1, 'name' => 'new']]
```

**Generated SQL:**
```sql
UPDATE users SET name = ? WHERE id = ? RETURNING id, name
```

### DELETE

```php
$query = $this->entityManager->createQueryBuilder()
    ->delete(User::class, 'u')
    ->where('u.id = :id')
    ->setParameter('id', 1)
    ->getQuery();

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);
$query->setHint(ReturningWalker::RETURNING_CLAUSE, new ReturningClause());

$rows = $query->getArrayResult(); // [['id' => 1, 'name' => 'new']]
```

**Generated SQL:**
```sql
DELETE FROM users WHERE id = ? RETURNING *
```

### Single-result methods

> [!WARNING]
> The rows are modified before Doctrine checks how many of them came back. `getSingleResult()`, `getOneOrNullResult()` and `getSingleScalarResult()` throw `NonUniqueResultException` or `NoResultException` when the rows are already updated or deleted.

To leave the data unchanged in that case, run the query in a transaction:

```php
$row = $this->entityManager->wrapInTransaction(
    static fn (): mixed => $query->getSingleResult(),
);
```

On an exception `wrapInTransaction()` rolls back and closes the entity manager. To keep using it, wrap the query in `$this->entityManager->getConnection()->transactional(...)` instead.

### Limitations

- `SELECT` is not supported: PostgreSQL has no `RETURNING` for it. The walker throws `ReturningWalkerException`.
- Entities with `JOINED` inheritance are not supported: Doctrine runs such statements as several queries over a temporary table. The walker throws `ReturningWalkerException`.
- The result cache (`enableResultCache()`) is ignored, otherwise a cache hit would skip the modification.

---

## `TABLESAMPLE`

Adds a `TABLESAMPLE` clause to the `FROM` clause with support for `BERNOULLI` and `SYSTEM` sampling methods. Useful for sampling rows randomly.

### Example

```php
$query = $this->entityManager->createQueryBuilder()
    ->select('u')
    ->from(User::class, 'u')
    ->orderBy('u.name', 'DESC')
    ->getQuery();

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, TablesampleWalker::class);
$query->setHint(TablesampleWalker::TABLESAMPLE_RULE, [User::class => new Tablesample(TablesampleMethod::BERNOULLI, 0.1)]);

$query->getSQL();
```

**Generated SQL:**
```sql
SELECT u0_.id AS id_0, u0_.name AS name_1 FROM users u0_ TABLESAMPLE BERNOULLI(0.1) ORDER BY u0_.name DESC
```

---

## `WITH TIES`

Replaces `LIMIT` / `OFFSET` with SQL standard `FETCH NEXT ... ROWS WITH TIES`. Useful for pagination that includes ties.

### Example

```php
$query = $this->entityManager->createQueryBuilder()
    ->select('u')
    ->from(User::class, 'u')
    ->orderBy('u.name', 'DESC')
    ->getQuery()
    ->setMaxResults(5);

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, WithTiesWalker::class);

$query->getSQL();
```

**Generated SQL:**
```sql
SELECT u0_.id AS id_0, u0_.name AS name_1 FROM users u0_ ORDER BY u0_.name DESC FETCH NEXT 5 ROWS WITH TIES
```

---

## Development

Unit tests compare generated SQL and need no database:

```bash
vendor/bin/phpunit
```

Integration tests run the queries against PostgreSQL in Docker:

```bash
docker compose run --rm php composer install
docker compose run --rm php vendor/bin/phpunit --testsuite integration
```

To test another PostgreSQL version, recreate the database container:

```bash
POSTGRES_VERSION=13 docker compose up -d --force-recreate postgres
```

Without `DATABASE_URL` the integration tests are skipped. In CI they fail instead.
