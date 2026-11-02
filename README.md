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

Adds a `RETURNING` clause to DQL queries (`SELECT`/`UPDATE`/`DELETE`). Useful when you need to return data from modified rows (PostgreSQL).

Accepts `'*'` (all columns) or an array of specific column names, e.g. `['id', 'name']`.

### SELECT

#### Example

```php
$query = $this->entityManager->createQueryBuilder()
    ->select('u')
    ->from(User::class, 'u')
    ->where('u.id = 1')
    ->getQuery();

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);
$query->setHint(ReturningWalker::RETURNING_CLAUSE, new ReturningClause());

$query->getSQL();
```

**Generated SQL:**
```sql
SELECT u0_.id AS id_0, u0_.name AS name_1 FROM users u0_ WHERE u0_.id = 1 RETURNING *
```

### UPDATE

#### Example

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

$query->getSQL();
```

**Generated SQL:**
```sql
UPDATE users SET name = 'new' WHERE id = 1 RETURNING id, name
```

### DELETE

#### Example

```php
$query = $this->entityManager->createQueryBuilder()
    ->delete(User::class, 'u')
    ->where('u.id = :id')
    ->setParameter('id', 1)
    ->getQuery();

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);
$query->setHint(ReturningWalker::RETURNING_CLAUSE, new ReturningClause());

$query->getSQL();
```

**Generated SQL:**
```sql
DELETE FROM users WHERE id = 1 RETURNING *
```

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
