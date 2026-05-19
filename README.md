[![codecov](https://codecov.io/gh/tugmaks/doctrine-walkers/graph/badge.svg?token=4YXA0059QT)](https://codecov.io/gh/tugmaks/doctrine-walkers)
# Doctrine walkers

A collection of custom Doctrine ORM output walkers for commonly used SQL clauses. Each walker can be applied per-query via a query hint without modifying your entities or repository code.

## Installation

```bash
composer require tugmaks/doctrine-walkers
```

## Walkers

- [Locking](#locking-walker)
- [Nulls](#nulls-walker)
- [Returning](#returning-walker)
- [Tablesample](#tablesample-walker)
- [With ties](#with-ties-walker)

---

## Locking walker

Adds a `FOR UPDATE` clause with configurable lock strength and options (`SKIP LOCKED`, `NOWAIT`). Useful for pessimistic locking.

### Example via DQL

```php
$query = $this->entityManager->createQuery('SELECT u FROM App\Entity\User u WHERE id = 1');

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, LockingWalker::class);
$query->setHint(LockingWalker::LOCKING_CLAUSE, new LockingClause(LockStrength::UPDATE, Option::SKIP_LOCKED));

$query->getSQL();
```

or

### Example via QueryBuilder

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

## NULLS walker

Adds `NULLS FIRST` / `NULLS LAST` to `ORDER BY` clauses. Useful for controlling null sorting order in PostgreSQL.

### Example via DQL

```php
$query = $this->entityManager->createQuery('SELECT u FROM App\Entity\User u ORDER BY u.name DESC');

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
$query->setHint(NullsWalker::NULLS_RULE, ['u.name' => NULLS::LAST]);

$query->getSQL();
```

or

### Example via QueryBuilder

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

---

## RETURNING walker

Adds a `RETURNING` clause to DQL queries (`SELECT`/`UPDATE`/`DELETE`). Useful when you need to return data from modified rows (PostgreSQL).

Accepts `'*'` (all columns) or an array of specific column names, e.g. `['id', 'name']`.

### SELECT

#### Example via DQL

```php
$query = $this->entityManager->createQuery('SELECT u FROM App\Entity\User u WHERE u.id = 1');

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);
$query->setHint(ReturningWalker::RETURNING_CLAUSE, new ReturningClause('*'));

$query->getSQL();
```

or

#### Example via QueryBuilder

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

#### Example via DQL

```php
$query = $this->entityManager->createQuery("UPDATE App\Entity\User u SET u.name = 'new' WHERE u.id = 1");

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);
$query->setHint(ReturningWalker::RETURNING_CLAUSE, new ReturningClause(['id', 'name']));

$query->getSQL();
```

or

#### Example via QueryBuilder

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

#### Example via DQL

```php
$query = $this->entityManager->createQuery('DELETE App\Entity\User u WHERE u.id = 1');

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, ReturningWalker::class);
$query->setHint(ReturningWalker::RETURNING_CLAUSE, new ReturningClause());

$query->getSQL();
```

or

#### Example via QueryBuilder

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

## Tablesample walker

Adds a `TABLESAMPLE` clause to the `FROM` clause with support for `BERNOULLI` and `SYSTEM` sampling methods. Useful for sampling rows randomly.

### Example via DQL

```php
$query = $this->entityManager->createQuery('SELECT u FROM App\Entity\User u ORDER BY u.name DESC');

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, TablesampleWalker::class);
$query->setHint(TablesampleWalker::TABLESAMPLE_RULE, [User::class => new Tablesample(TablesampleMethod::BERNOULLI, 0.1) ]);

$query->getSQL();
```

or

### Example via QueryBuilder

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

## `WITH TIES` walker

Replaces `LIMIT` / `OFFSET` with SQL standard `FETCH NEXT ... ROWS WITH TIES`. Useful for pagination that includes ties.

### Example via DQL

```php
$query = $this->entityManager->createQuery('SELECT u FROM App\Entity\User u ORDER BY u.name DESC')->setMaxResults(5);

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, WithTiesWalker::class);

$query->getSQL();
```

or

### Example via QueryBuilder

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
